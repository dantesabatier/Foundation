<?php

declare(strict_types=1);

namespace Sabatier\Foundation\Networking;

/**
 * The entities that can make a network request.
 * Use one of these values when setting the attribution parameter of a {@see URLRequest}. If you don't set a value, the system assumes URLRequestAttribution::developer.
 */
enum URLRequestAttribution: int
{
    /** A developer-initiated network request. Use this value for the attribution parameter of a URL request that your app makes for any purpose other than when the user explicitly accesses a link. This includes requests that your app makes to get user data. This is the default value.
     * For cases where the user enters a URL, like in the navigation bar of a web browser, or taps or clicks a URL to load the content it represents, use URLRequestAttribution::user value instead. */
    case developer = 0;
    /** The user explicitly directs the app to make a network request. Use this value for the attribution parameter of a URL request that satisfies a user request to access an explicit, unmodified URL. In all other cases, use the URLRequestAttribution::developer value instead. */
    case user = 1;
}
