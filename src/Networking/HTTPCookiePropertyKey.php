<?php

declare(strict_types=1);

namespace Sabatier\Foundation\Networking;

/**
 * Constants that define the supported keys in a cookie attributes dictionary.
 */
final class HTTPCookiePropertyKey
{
    /** @var string A string containing the comment for the cookie. */
    const string comment = "Comment";
    /** @var string A URL object or string containing the comment URL for the cookie. */
    const string commentURL = "CommentURL";
    /** @var string A string stating whether the cookie should be discarded at the end of the session. */
    const string discard = "Discard";
    /** @var string A string containing the domain for the cookie. */
    const string domain = "Domain";
    /** @var string A Date or string specifying the expiration date for the cookie. */
    const string expires = "Expires";
    /** @var string A string containing an integer value stating how long in seconds the cookie should be kept, at most. */
    const string maximumAge = "Max-Age";
    /** @var string string containing the name of the cookie (required). */
    const string name = "Name";
    /** @var string A URL or string containing the URL that set this cookie. */
    const string originURL = "OriginURL";
    /** @var string A string containing the path for the cookie. */
    const string path = "Path";
    /** @var string A string containing comma-separated integer values specifying the ports for the cookie. */
    const string port = "Port";
    /** @var string A string indicating the same-site policy for the cookie. */
    const string sameSitePolicy = "SameSite";
    /** @var string A string indicating that the cookie should be transmitted only over secure channels. */
    const string secure = "Secure";
    /** @var string A string containing the value of the cookie. */
    const string value = "Value";
    /** @var string A string that specifies the version of the cookie. */
    const string version = "Version";
    /** @internal */
    const string created = "Created";
    const string httpOnly = "HttpOnly";
    const string lifetime = "lifetime";
}
