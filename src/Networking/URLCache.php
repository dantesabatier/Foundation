<?php

namespace Sabatier\Foundation\Networking;

use Closure;
use Exception;
use Sabatier\Foundation\ArrayClass;
use Sabatier\Foundation\Bundle;
use Sabatier\Foundation\Date;
use Sabatier\Foundation\Dictionary;
use Sabatier\Foundation\DirectoryEnumerationOptions;
use Sabatier\Foundation\Error;
use Sabatier\Foundation\FileManager;
use Sabatier\Foundation\KeyedArchiver;
use Sabatier\Foundation\KeyedUnarchiver;
use Sabatier\Foundation\ObjectClass;
use Sabatier\Foundation\ProcessInfo;
use Sabatier\Foundation\Range;
use Sabatier\Foundation\SearchPathDirectory;
use Sabatier\Foundation\SearchPathDomainMask;
use Sabatier\Foundation\Set;
use Sabatier\Foundation\URL;
use Sabatier\Foundation\URLResourceKey;

/**
 * An object that maps URL requests to cached response objects.
 * @psalm-consistent-constructor
 */
class URLCache extends ObjectClass
{
    private static ?URLCache $shared = null;
    /** @var int The current size of the on-disk cache, in bytes. */
    public readonly int $currentDiskUsage;
    /** @var int The current size of the in-memory cache, in bytes. */
    public readonly int $currentMemoryUsage;
    private readonly ?URL $cacheDirectory;
    /** @var ArrayClass<string> */
    private readonly ArrayClass $inMemoryCacheOrder;
    /** @var Dictionary<CacheEntry> */
    private readonly Dictionary $inMemoryCacheContents;

    /**
     * Creates a URL cache object with the specified memory and disk capacities, in the specified directory.
     *
     * A disk cache measured in the tens of megabytes is acceptable in most cases.
     * @param int $memoryCapacity The memory capacity of the cache, in bytes.
     * @param int $diskCapacity The disk capacity of the cache, in bytes.
     * @param URL|null $directory The path to an on-disk directory, where the system stores the on-disk cache. If directory is nil, the cache uses a default directory.
     */
    public function __construct(public int $memoryCapacity, public int $diskCapacity, ?URL $directory = null)
    {
        if (!$directory instanceof URL) {
            try {
                $fileManager = FileManager::default();
                $caches = $fileManager->url(SearchPathDirectory::cachesDirectory, SearchPathDomainMask::local, null, true);
                $directoryName = str_replace(["/", "\\", ":"], "-", Bundle::main()->bundleIdentifier ?? ProcessInfo::processInfo()->processName);
                $url = $caches->appendingPathComponent($directoryName);
                if (!$fileManager->fileExists($url->path)) {
                    $fileManager->createDirectory($url, true);
                }
                $this->cacheDirectory = $url;
            } catch (Exception) {
                $this->cacheDirectory = null;
            }
        } else {
            $this->cacheDirectory = $directory;
        }
        $this->inMemoryCacheOrder = new ArrayClass();
        $this->inMemoryCacheContents = new Dictionary();
    }

    private function evictFromMemoryCacheAssumingLockHeld(int $maximumSize): void
    {
        $totalSize = $this->inMemoryCacheContents->reduce(0, fn(int &$size, CacheEntry $entry): int => $size += $entry->cost);
        $countEvicted = 0;
        foreach ($this->inMemoryCacheOrder as $identifier) {
            if ($totalSize > $maximumSize) {
                $countEvicted += 1;
                /** @var CacheEntry $entry */
                $entry = $this->inMemoryCacheContents->removeValueForKey($identifier);
                $totalSize -= $entry->cost;
            } else {
                break;
            }
        }
        $this->inMemoryCacheOrder->removeSubrange(new Range(0, $countEvicted));
    }

    private function evictFromDiskCache(int $maximumSize): void
    {
        $keys = new ArrayClass([URLResourceKey::fileSizeKey]);
        $entries = $this->diskEntries($keys);
        $entries->sort(fn(DiskEntry $e0, DiskEntry $e1): int => $e0->date->compare($e1->date)->value);
        $sizes = $entries->map(fn(DiskEntry $entry): int => $entry->url->resourceValues(new Set($keys))->fileSize ?? 0);
        $totalSize = $sizes->sum();
        foreach ($entries as $index => $entry) {
            if ($totalSize > $maximumSize) {
                try {
                    FileManager::default()->removeItem($entry->url);
                    $totalSize -= $sizes[$index];
                } catch (Exception) {
                }
            }
        }
    }

    private function identifier(URLRequest $request): ?string
    {
        if (!($host = $request->url->host)) {
            return null;
        }
        $data = strtolower($host);
        $data .= "\0";
        $data .= $request->url->port ?? -1;
        $data .= "\0";
        $data .= $request->url->path;
        return base64_encode($data);
    }

    /**
     * @param Closure(DiskEntry, bool=): void $block
     * @param ArrayClass<string> $keys
     */
    private function enumerateDiskEntries(Closure $block, ArrayClass $keys = new ArrayClass()): void
    {
        if (!($directory = $this->cacheDirectory)) {
            return;
        }
        if ($enumerator = FileManager::default()->enumerator($directory, $keys, DirectoryEnumerationOptions::skipsSubdirectoryDescendants | DirectoryEnumerationOptions::skipsPackageDescendants | DirectoryEnumerationOptions::skipsHiddenFiles, fn(URL $url, Error $error): bool => false)) {
            foreach ($enumerator as $url) {
                if ($entry = DiskEntry::entry($url)) {
                    $stop = false;
                    $block($entry, $stop);
                    /** @psalm-suppress TypeDoesNotContainType */
                    if (/** @phpstan-ignore-line */ $stop) {
                        break;
                    }
                }
            }
        }
    }

    /**
     * @param ArrayClass<string> $keys
     * @return ArrayClass<DiskEntry>
     */
    private function diskEntries(ArrayClass $keys = new ArrayClass()): ArrayClass
    {
        /** @var ArrayClass<DiskEntry> $entries */
        $entries = new ArrayClass();
        $this->enumerateDiskEntries(function (DiskEntry $entry) use ($entries): void {
            $entries->append($entry);
        }, $keys);
        return $entries;
    }

    /**
     * @param URLRequest $request
     * @param Date|null $date
     * @return object|null
     */
    private function diskContentLocators(URLRequest $request, ?Date $date = null): ?object
    {
        if (!($directory = $this->cacheDirectory) || !($identifier = $this->identifier($request))) {
            return null;
        }
        if ($date) {
            return (object)["identifier" => $identifier, "url" => $directory->appendingPathComponent("$date->timeIntervalSinceReferenceDate.$identifier." . DiskEntry::pathExtension)];
        }
        $foundURL = null;
        $this->enumerateDiskEntries(function (DiskEntry $entry, bool &$stop) use ($identifier, &$foundURL): void {
            if ($entry->identifier === $identifier) {
                $foundURL = $entry->url;
                $stop = true;
            }
        });
        if ($foundURL instanceof URL) {
            return (object)["identifier" => $identifier, "url" => $foundURL];
        }
        return null;
    }

    /**
     * @param URLRequest $request
     * @return StoredCachedURLResponse|null
     * @throws Exception
     */
    private function diskContents(URLRequest $request): ?StoredCachedURLResponse
    {
        if (!($url = $this->diskContentLocators($request)?->url) || !($data = FileManager::default()->contents($url->path))) {
            return null;
        }
        return KeyedUnarchiver::unarchiveTopLevelObjectWithData($data);
    }

    /**
     * The shared URL cache instance.
     *
     * If your app doesn't have special caching requirements or constraints, the default shared cache instance should be acceptable. Alternatively, you can create a custom URLCache object and set it as the shared cache instance. You should do so before making any calls to this method.
     * @return URLCache
     */
    public static function shared(): URLCache
    {
        if (static::$shared === null) {
            static::$shared = new static(4 * 1024 * 1024, 20 * 1024 * 1024);
        }
        return static::$shared;
    }

    /**
     * Returns the cached URL response in the cache for the specified URL request.
     *
     * If you override this method, you should also override {@see getCachedResponse()}.
     * @param URLRequest $request The URL request whose cached response is desired.
     * @return CachedURLResponse|null The cached URL response for request, or nil if no response has been cached.
     */
    public function cachedResponse(URLRequest $request): ?CachedURLResponse
    {
        if ($result = (function () use ($request): ?CachedURLResponse {
            if ($identifier = $this->identifier($request)) {
                $entry = $this->inMemoryCacheContents[$identifier];
                return $entry?->cachedURLResponse;
            }
            return null;
        })()) {
            return $result;
        }
        try {
            if (!($contents = $this->diskContents($request))) {
                return null;
            }
            return $contents->cachedURLResponse;
        } catch (Exception) {
        }
        return null;
    }

    /**
     * Stores a cached URL response for a specified request.
     *
     * @param CachedURLResponse $cachedResponse The cached URL response to store.
     * @param URLRequest $request The request for which the cached URL response is being stored.
     */
    public function storeCachedResponse(CachedURLResponse $cachedResponse, URLRequest $request): void
    {
        $inMemory = $cachedResponse->storagePolicy === URLCacheStoragePolicy::allowed || $cachedResponse->storagePolicy === URLCacheStoragePolicy::allowedInMemoryOnly;
        $onDisk = $cachedResponse->storagePolicy === URLCacheStoragePolicy::allowed;
        if (!$inMemory && !$onDisk) {
            return;
        }
        if (!($identifier = $this->identifier($request))) {
            return;
        }
        $object = new StoredCachedURLResponse($cachedResponse);
        try {
            $serialized = ($onDisk && $this->diskCapacity > 0) ? KeyedArchiver::archivedData($object) : null;
        } catch (Exception) {
            $serialized = null;
        }
        $entry = new CacheEntry($identifier, $cachedResponse, $serialized);
        if ($inMemory && $entry->cost < $this->memoryCapacity) {
            $this->evictFromMemoryCacheAssumingLockHeld($this->memoryCapacity - $entry->cost);
            $this->inMemoryCacheOrder->append($identifier);
            $this->inMemoryCacheContents->setValueForKey($entry, $identifier);
        }
        if ($onDisk && $serialized && $entry->cost < $this->diskCapacity) {
            try {
                $this->evictFromDiskCache($this->diskCapacity - $entry->cost);
                $locators = $this->diskContentLocators($request, new Date());
                if ($newURL = $locators?->url) {
                    FileManager::default()->createFile($newURL->path, $serialized);
                }
                if ($identifier = $locators?->identifier) {
                    $entriesToRemove = $this->diskEntries()->filter(fn(DiskEntry $entry): bool => $entry->identifier === $identifier)->sort(fn(DiskEntry $e0, DiskEntry $e1): int => $e0->date->compare($e1->date)->value);
                    $entriesToRemove->popFirst();
                    foreach ($entriesToRemove as $entry) {
                        FileManager::default()->removeItem($entry->url);
                    }
                }
            } catch (Exception) {
            }
        }
    }

    /**
     * Gets the cached URL response for a data task, passing it to the provided completion handler.
     *
     * @param URLSessionDataTask $dataTask The data task whose cached URL response is desired.
     * @param Closure(CachedURLResponse|null): void $completionHandler A completion handler that receives the cached URL response for the data task's request, or nil if no response is found in the cache.
     */
    public function getCachedResponse(URLSessionDataTask $dataTask, Closure $completionHandler): void
    {
        if (!($request = $dataTask->currentRequest)) {
            $completionHandler(null);
            return;
        }
        $completionHandler($this->cachedResponse($request));
    }

    /**
     * Stores a cached URL response for a specified data task.
     *
     * @param CachedURLResponse $cachedResponse The cached URL response to store for this data task.
     * @param URLSessionDataTask $dataTask The data task whose response is to be cached.
     */
    public function storeCachedResponseForDataTask(CachedURLResponse $cachedResponse, URLSessionDataTask $dataTask): void
    {
        if (!($request = $dataTask->currentRequest)) {
            return;
        }
        $this->storeCachedResponse($cachedResponse, $request);
    }

    /**
     * Removes the cached URL response for a specified URL request.
     *
     * If you override this method, you should also override {@see removeCachedResponseFor()}.
     * @param URLRequest $request The URL request whose cached URL response should be removed. If there is no corresponding cached URL response, no action is taken.
     */
    public function removeCachedResponse(URLRequest $request): void
    {
        if (!($identifier = $this->identifier($request))) {
            return;
        }
        if ($this->inMemoryCacheContents[$identifier]) {
            $this->inMemoryCacheOrder->removeAll(fn(string $e): bool => $e === $identifier);
            $this->inMemoryCacheContents->removeValueForKey($identifier);
        }
        if ($url = $this->diskContentLocators($request)?->url) {
            try {
                FileManager::default()->removeItem($url);
            } catch (Exception) {
            }
        }
    }

    /**
     * Removes the cached URL response for a specified data task.
     *
     * @param URLSessionDataTask $dataTask A task whose URL request's corresponding cached URL response should be removed. If there is no corresponding cached URL response, no action is taken.
     */
    public function removeCachedResponseForDataTask(URLSessionDataTask $dataTask): void
    {
        if (!($request = $dataTask->currentRequest)) {
            return;
        }
        $this->removeCachedResponse($request);
    }

    /**
     * Clears the given cache of any cached responses since the provided date.
     *
     * @param Date $date The earliest date of responses that should remain in the cache. Any responses with dates later than this parameter should be removed.
     */
    public function removeCachedResponses(Date $date): void
    {
        /** @var Set<string> $identifiersToRemove */
        $identifiersToRemove = new Set();
        /** @var CacheEntry $entry */
        foreach ($this->inMemoryCacheContents as $identifier => $entry) {
            if ($entry->date->timeIntervalSinceReferenceDate > $date->timeIntervalSinceReferenceDate) {
                $identifiersToRemove->append($identifier);
            }
        }
        foreach ($identifiersToRemove as $identifier) {
            $this->inMemoryCacheContents->removeValueForKey($identifier);
        }
        $this->inMemoryCacheOrder->removeAll(fn(string $e): bool => $identifiersToRemove->containsElement($e));
        try {
            $entriesToRemove = $this->diskEntries()->filter(fn(DiskEntry $e): bool => $e->date->timeIntervalSinceReferenceDate > $date->timeIntervalSinceReferenceDate);
            foreach ($entriesToRemove as $entry) {
                FileManager::default()->removeItem($entry->url);
            }
        } catch (Exception) {
        }
    }

    /**
     * Clears the receiver's cache, removing all stored cached URL responses.
     */
    public function removeAllCachedResponses(): void
    {
        $this->inMemoryCacheContents->removeAll();
        $this->inMemoryCacheOrder->removeAll();
        $this->evictFromDiskCache(0);
    }
}
