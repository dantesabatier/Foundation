<?php

declare(strict_types=1);

namespace Sabatier\Foundation\Networking;

/** @internal */
final readonly class StoredCachedURLResponse
{
    public function __construct(public CachedURLResponse $cachedURLResponse)
    {
    }
}
