<?php

namespace Sabatier\Foundation\Networking;

/** @internal */
final readonly class StoredCachedURLResponse
{
    public function __construct(public CachedURLResponse $cachedURLResponse)
    {
    }
}
