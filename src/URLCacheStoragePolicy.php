<?php

namespace Sabatier\Foundation;

/**
 * These constants specify the caching strategy used by an {@see CachedURLResponse} object.
 */
enum URLCacheStoragePolicy: int
{
    /** Storage in URLCache is allowed without restriction. */
    case allowed = 0;
    /** Storage in {@see URLCache} is allowed; however storage should be restricted to memory only. */
    case allowedInMemoryOnly = 1;
    /** Storage in {@see URLCache} is not allowed in any fashion, either in memory or on disk. */
    case notAllowed = 2;
}
