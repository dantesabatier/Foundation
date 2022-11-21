<?php

namespace Sabatier\Foundation\Networking;

use Sabatier\Foundation\Date;
use Sabatier\Foundation\Dictionary;
use Sabatier\Foundation\ObjectClass;

/**
 * A cached response to a URL request.
 */
class CachedURLResponse extends ObjectClass
{
    /** @internal */
    public Date $date;

    /**
     * @param URLResponse $response The response to cache.
     * @param string $data The data to cache.
     * @param URLCacheStoragePolicy $storagePolicy The storage policy for the cached
     * @param Dictionary<mixed>|null $userInfo An optional dictionary of user information. May be nil.response.
     */
    public function __construct(public readonly URLResponse $response, public readonly string $data, public readonly URLCacheStoragePolicy $storagePolicy = URLCacheStoragePolicy::allowed, public readonly ?Dictionary $userInfo = null)
    {
        $this->date = new Date();
    }
}
