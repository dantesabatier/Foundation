<?php

namespace Sabatier\Foundation\Networking;

/** @internal */
class StoredCachedURLResponse
{
    public function __construct(public readonly CachedURLResponse $cachedURLResponse)
    {
    }
}
