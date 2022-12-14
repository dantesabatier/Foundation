<?php

namespace Sabatier\Foundation\Networking;

/** @internal */
readonly class StoredCachedURLResponse
{
    public function __construct(public CachedURLResponse $cachedURLResponse)
    {
    }
}
