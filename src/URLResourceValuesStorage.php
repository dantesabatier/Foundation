<?php

declare(strict_types=1);

namespace Sabatier\Foundation;

use Exception;
use JetBrains\PhpStorm\ExpectedValues;
use SplFileInfo;

/** @internal */
final class URLResourceValuesStorage
{
    private Dictionary $valuesCache;

    public function __construct()
    {
        $this->valuesCache = new Dictionary();
    }

    public function removeAllCachedResourceValues(): void
    {
        $this->valuesCache->removeAll();
    }

    public function removeCachedResourceValue(#[ExpectedValues(valuesFromClass: URLResourceKey::class)] string $key): void
    {
        $this->valuesCache->removeValueForKey($key);
    }

    public function setTemporaryResourceValue(mixed $value, #[ExpectedValues(valuesFromClass: URLResourceKey::class)] string $key): void
    {
        $this->valuesCache[$key] = $value;
    }

    public function getResourceValue(mixed &$value, #[ExpectedValues(valuesFromClass: URLResourceKey::class)] string $key, URL $url): void
    {
        $cache = $this->valuesCache[$key];
        if ($cache !== null) {
            $value = $cache;
            return;
        }
        $fetchedValues = $this->read(new Set([$key]), $url);
        $fetched = $fetchedValues[$key];
        if ($fetched !== null) {
            $value = $fetched;
            $this->valuesCache[$key] = $fetched;
            return;
        }
        $value = null;
    }

    public function resourceValues(Set $keys, URL $url): Dictionary
    {
        $result = new Dictionary();
        $keysToFetch = new Set();
        foreach ($keys as $key) {
            $value = $this->valuesCache[$key];
            if ($value !== null) {
                $result[$key] = $value;
            } else {
                $keysToFetch->insert($key);
            }
        }
        if (!$keysToFetch->isEmpty) {
            $found = $this->read($keysToFetch, $url)->compactMapValues(fn(mixed $value): mixed => $value);
            $this->valuesCache->merge($found);
            $result->merge($found);
        }
        return $result;
    }

    public function setResourceValue(mixed $value, #[ExpectedValues(valuesFromClass: URLResourceKey::class)] string $key, URL $url): void
    {
        $this->write(new Dictionary([$key => $value]), $url);
        $this->valuesCache[$key] = $value;
    }

    public function setResourceValues(Dictionary $values, URL $url): void
    {
        $this->write($values, $url);
        $this->valuesCache->merge($values);
    }

    public function read(Set $keys, URL $url): Dictionary
    {
        if (!$url->isFileURL) {
            fatal_error();
        }
        $path = $url->path;
        /** @var Dictionary<mixed> $result */
        $result = new Dictionary();
        $info = new SplFileInfo($path);
        $isDirectoryJunction = null;
        /** @var string $key */
        foreach ($keys as $key) {
            if ($key === URLResourceKey::isDirectoryKey) {
                $result[$key] = $info->isDir() || ($isDirectoryJunction ??= $this->isDirectoryJunction($path));
            } elseif ($key === URLResourceKey::parentDirectoryURLKey) {
                if ($directory = $info->getPathInfo()?->getRealPath()) {
                    $result[$key] = URL::fileURL($directory);
                }
            } elseif ($key === URLResourceKey::fileResourceTypeKey) {
                $result[$key] = $info->getType();
            } elseif ($key === URLResourceKey::isAliasFileKey || $key === URLResourceKey::isSymbolicLinkKey) {
                $result[$key] = $info->isLink() || ($isDirectoryJunction ??= $this->isDirectoryJunction($path));
            } elseif ($key === URLResourceKey::fileSizeKey) {
                $result[$key] = $info->getSize();
            } elseif ($key === URLResourceKey::isRegularFileKey) {
                $result[$key] = $info->isFile();
            } elseif ($key === URLResourceKey::isPackageKey) {
                // A directory carrying an extension is opaque by convention, the way ".momd" or ".rtfd" are: the extension is what declares it a unit rather than a folder to descend into. Nothing registers those extensions, so the shape of the name is all there is to go on.
                $result[$key] = $info->isDir() && $url->pathExtension !== "";
            } elseif ($key === URLResourceKey::attributeModificationDateKey) {
                $result[$key] = Date::dateWithTimeIntervalSince1970((float)$info->getMTime());
            } elseif ($key === URLResourceKey::creationDateKey) {
                $result[$key] = Date::dateWithTimeIntervalSince1970((float)$info->getCTime());
            } elseif ($key === URLResourceKey::isExecutableKey) {
                $result[$key] = $info->isExecutable();
            } elseif ($key === URLResourceKey::isHiddenKey) {
                $result[$key] = is_hidden($path);
            } elseif ($key === URLResourceKey::isReadableKey) {
                $result[$key] = $info->isReadable();
            } elseif ($key === URLResourceKey::isWritableKey) {
                $result[$key] = $info->isWritable();
            } elseif ($key === URLResourceKey::nameKey) {
                $result[$key] = $info->getFilename();
            } elseif ($key === URLResourceKey::pathKey) {
                $result[$key] = $info->getPathname();
            }
        }
        return $result;
    }

    private function isDirectoryJunction(string $path): bool
    {
        if (!TARGET_OS_WINDOWS || is_link($path) || !file_exists($path)) {
            return false;
        }
        $attributes = lstat($path);
        // PHP reports Windows directory junctions with a lstat(...) mode of 0.
        return $attributes !== false && $attributes["mode"] === 0;
    }

    public function write(Dictionary $keysAndValues, URL $url): void
    {
        /** @var string|null $name */
        $name = $keysAndValues[URLResourceKey::nameKey];
        if ($name) {
            try {
                FileManager::default()->moveItem($url, $url->deletingLastPathComponent()->appendingPathComponent($name));
            } catch (Exception) {
            }
        }
    }
}
