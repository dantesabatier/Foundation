<?php

namespace Sabatier\Foundation;

use Closure;
use Exception;

/**
 * An object that maps URL requests to cached response objects.
 */
class URLCache extends ObjectClass
{
    private static ?URLCache $shared = null;

    /** @var int The current size of the on-disk cache, in bytes. */
    public readonly int $currentDiskUsage;
    /** @var int The current size of the in-memory cache, in bytes. */
    public readonly int $currentMemoryUsage;
    private readonly ?URL $cacheDirectory;

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
        if (!$directory) {
            try {
                $caches = FileManager::default()->url(SearchPathDirectory::cachesDirectory, SearchPathDomainMask::local, null, true);
                $directoryName = str_replace(["/", "\\", ":"], "-", Bundle::main()->bundleIdentifier ?? ProcessInfo::processInfo()->processName);
                $url = $caches->appendingPathComponent($directoryName);
                if (!FileManager::default()->fileExists($url->path)) {
                    FileManager::default()->createDirectory($url, true);
                }
                $this->cacheDirectory = $url;
            } catch (Exception) {
                $this->cacheDirectory = null;
            }
        } else {
            $this->cacheDirectory = $directory;
        }
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
            static::$shared = new URLCache(4 * 1024 * 1024, 20 * 1024 * 1024);
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
    public function cachedResponse(/** @noinspection PhpUnusedParameterInspection */ URLRequest $request): ?CachedURLResponse
    {
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
    }

    /**
     * Gets the cached URL response for a data task, passing it to the provided completion handler.
     *
     * @param URLSessionDataTask $dataTask The data task whose cached URL response is desired.
     * @param Closure(CachedURLResponse|null): void $completionHandler A completion handler that receives the cached URL response for the data task's request, or nil if no response is found in the cache.
     */
    public function getCachedResponse(URLSessionDataTask $dataTask, Closure $completionHandler): void
    {
    }

    /**
     * Stores a cached URL response for a specified data task.
     *
     * @param CachedURLResponse $cachedResponse The cached URL response to store for this data task.
     * @param URLSessionDataTask $dataTask The data task whose response is to be cached.
     */
    public function storeCachedResponseForDataTask(CachedURLResponse $cachedResponse, URLSessionDataTask $dataTask): void
    {
    }

    /**
     * Removes the cached URL response for a specified URL request.
     *
     * If you override this method, you should also override {@see removeCachedResponseFor()}.
     * @param URLRequest $request The URL request whose cached URL response should be removed. If there is no corresponding cached URL response, no action is taken.
     */
    public function removeCachedResponse(URLRequest $request): void
    {
    }

    /**
     * Removes the cached URL response for a specified data task.
     *
     * @param URLSessionDataTask $dataTask A task whose URL request's corresponding cached URL response should be removed. If there is no corresponding cached URL response, no action is taken.
     */
    public function removeCachedResponseForDataTask(URLSessionDataTask $dataTask): void
    {
    }

    /**
     * Clears the given cache of any cached responses since the provided date.
     *
     * @param Date $date The earliest date of responses that should remain in the cache. Any responses with dates later than this parameter should be removed.
     */
    public function removeCachedResponses(Date $date): void
    {
    }

    /**
     * Clears the receiver's cache, removing all stored cached URL responses.
     */
    public function removeAllCachedResponses(): void
    {
    }
}
