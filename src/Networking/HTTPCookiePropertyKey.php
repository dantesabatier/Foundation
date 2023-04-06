<?php

namespace Sabatier\Foundation\Networking;

/**
 * Constants that define the supported keys in a cookie attributes dictionary.
 */
class HTTPCookiePropertyKey
{
    /** @var string A string containing the comment for the cookie. */
    final const comment = "Comment";
    /** @var string A URL object or string containing the comment URL for the cookie. */
    final const commentURL = "CommentURL";
    /** @var string A string stating whether the cookie should be discarded at the end of the session. */
    final const discard = "Discard";
    /** @var string A string containing the domain for the cookie. */
    final const domain = "Domain";
    /** @var string A Date or string specifying the expiration date for the cookie. */
    final const expires = "Expires";
    /** @var string A string containing an integer value stating how long in seconds the cookie should be kept, at most. */
    final const maximumAge = "Max-Age";
    /** @var string string containing the name of the cookie (required). */
    final const name = "Name";
    /** @var string A URL or string containing the URL that set this cookie. */
    final const originURL = "OriginURL";
    /** @var string A string containing the path for the cookie. */
    final const path = "Path";
    /** @var string A string containing comma-separated integer values specifying the ports for the cookie. */
    final const port = "Port";
    /** @var string A string indicating the same-site policy for the cookie. */
    final const sameSitePolicy = "SameSite";
    /** @var string A string indicating that the cookie should be transmitted only over secure channels. */
    final const secure = "Secure";
    /** @var string A string containing the value of the cookie. */
    final const value = "Value";
    /** @var string A string that specifies the version of the cookie. */
    final const version = "Version";
    /** @internal */
    final const created = "Created";
    final const httpOnly = "HttpOnly";
}
