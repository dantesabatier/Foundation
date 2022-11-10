<?php

namespace Sabatier\Foundation;

/**
 * A cached response to a URL request.
 */
class CachedURLResponse extends ObjectClass
{
    /**
     * @param URLResponse $response The response to cache.
     * @param string $data The data to cache.
     * @param Dictionary<mixed>|null $userInfo An optional dictionary of user information. May be nil.
     * @param URLCacheStoragePolicy $storagePolicy The storage policy for the cached response.
     */
    public function __construct(public readonly URLResponse $response, public readonly string $data, public readonly ?Dictionary $userInfo = null, public readonly URLCacheStoragePolicy $storagePolicy = URLCacheStoragePolicy::allowed)
    {
    }
}
