<?php

declare(strict_types=1);

namespace Sabatier\Foundation\Networking;

enum URLRequestCachePolicy: int
{
    /** Use the caching logic defined in the protocol implementation, if any, for a particular URL load request. */
    case useProtocolCachePolicy = 0;
    /** The URL load should be loaded only from the originating source. */
    case reloadIgnoringCacheData = 1;
    /** Use existing cache data, regardless or age or expiration date, loading from originating source only if there is no cached data. */
    case returnCacheDataElseLoad = 2;
    /** Use existing cache data, regardless or age or expiration date, and fail if no cached data is available. */
    case returnCacheDataDontLoad = 3;
    /** Ignore local cache data, and instruct proxies and other intermediates to disregard their caches so far as the protocol allows. */
    case reloadIgnoringLocalAndRemoteCacheData = 4;
}
