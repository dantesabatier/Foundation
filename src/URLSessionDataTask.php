<?php

/**
 * @author Dante Sabatier <dantesabatier@me.com>
 * @version 1.0
 * @package Sabatier\Foundation
 */

namespace Sabatier\Foundation;

use Closure;

/**
 * Class URLSessionDataTask
 * A URL session task that returns downloaded data directly to the app in memory.
 * @package Sabatier\Foundation
 */
class URLSessionDataTask extends URLSessionTask
{
    /**
     * Creates a task that retrieves the contents of a URL based on the specified URL request object.
     * @param URLRequest $request A URL request object that provides request-specific information such as the URL, cache policy, request type, and body data or body stream.
     * @param Closure(string|null, URLResponse|null, Error|null): void $completion The completion handler to call when the load request is complete.
     * @return URLSessionDataTask The new session data task.
     */
    public static function dataTaskWithRequest(URLRequest $request, Closure $completion): URLSessionDataTask
    {
        return static::taskWithRequest($request, $completion);
    }
}
