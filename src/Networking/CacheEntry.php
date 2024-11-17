<?php

namespace Sabatier\Foundation\Networking;

use Sabatier\Foundation\Date;

/** @internal */
readonly class CacheEntry
{
    public Date $date;
    public int $cost;

    public function __construct(public string $identifier, public CachedURLResponse $cachedURLResponse, public ?string $serializedVersion = null)
    {
        $this->date = new Date();
        $this->cost = $serializedVersion ? strlen($serializedVersion) : (strlen($cachedURLResponse->data) + 500 * ($cachedURLResponse->userInfo?->count ?? 0));
    }
}
