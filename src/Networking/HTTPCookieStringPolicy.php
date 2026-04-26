<?php

declare(strict_types=1);

namespace Sabatier\Foundation\Networking;

/**
 * Values that indicate whether to restrict the cookie to requests sent back to the same site that created it.
 *
 * RFC 6265 defines "same site" as the registerable domain of a URI.
 */
final class HTTPCookieStringPolicy
{
    /** @var string A policy that prohibits a cross-site request from including the cookie. */
    final const string sameSiteStrict = "Strict";

    /** @var string A policy that allows certain cross-site requests to include the cookie. When a cookie has this policy, a request includes the cookie if the request is "top-level,", meaning one that changes the URL in the address bar. */
    final const string sameSiteLax = "Lax";
}
