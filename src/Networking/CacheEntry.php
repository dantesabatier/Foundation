<?php

namespace Sabatier\Foundation\Networking;

use Sabatier\Foundation\Date;

/** @internal */
class CacheEntry
{
    public readonly Date $date;
    private(set) int $cost;

    public function __construct(public readonly string $identifier, public readonly CachedURLResponse $cachedURLResponse, public readonly ?string $serializedVersion = null)
    {
        $this->date = new Date();
        $this->cost = $serializedVersion ? strlen($serializedVersion) : (strlen($cachedURLResponse->data) + 500 * ($cachedURLResponse->userInfo?->count ?? 0));
    }
}
