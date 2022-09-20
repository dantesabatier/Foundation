<?php

/**
 * Created by PhpStorm.
 * User: dante
 * Date: 08/05/20
 * Time: 17:02
 */

namespace Sabatier\Foundation;

use Closure;

/**
 * Class URLSessionUploadTask
 * @package Sabatier\Foundation
 */
class URLSessionUploadTask extends URLSessionDataTask
{
    /**
     * Creates a task that performs an HTTP request for uploading the specified file, then calls a handler upon completion.
     * @param URLRequest $request A URL request object that provides the URL, cache policy, request type, and so on. The body stream and body data in this request object are ignored.
     * @param URL $fileUrl The URL of the file to upload.
     * @param Closure(string|null, URLResponse|null, Error|null): void $completion The completion handler to call when the load request is complete.
     * @return URLSessionUploadTask
     */
    public static function uploadTaskWithRequest(URLRequest $request, URL $fileUrl, Closure $completion): URLSessionUploadTask
    {
        $request->httpBody = $fileUrl->path;
        return static::taskWithRequest($request, $completion);
    }
}
