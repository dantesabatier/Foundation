<?php

namespace Sabatier\Foundation;

use Closure;

/**
 * Interface URLSessionTaskDelegate
 * A protocol that defines methods that URL session instances call on their delegates to handle task-level events.
 * @package Sabatier\Foundation
 */
interface URLSessionTaskDelegate extends URLSessionDelegate
{
    /**
     * Tells the delegate that the task finished transferring data.
     * @param URLSession $session The session containing the task that has finished transferring data.
     * @param URLSessionTask $task The task that has finished transferring data.
     * @param Error|null $error If an error occurred, an error object indicating how the transfer failed, otherwise NULL.
     * The only errors your delegate receives through the error parameter are client-side errors, such as being unable to resolve the hostname or connect to the host. To check for server-side errors, inspect the {@see URLSessionTask::response()} property of the task parameter received by this callback.
     */
    public function urlSessionTaskDidComplete(URLSession $session, URLSessionTask $task, ?Error $error = null): void;

    /**
     * Tells the delegate that the remote server requested an HTTP redirect.
     * @param URLSession $session The session containing the task whose request resulted in a redirect.
     * @param URLSessionTask $task The task whose request resulted in a redirect.
     * @param HTTPURLResponse $response An object containing the server's response to the original request.
     * @param URLRequest $request A URL request object filled out with the new location.
     * @param Closure(?URLRequest): void $completionHandler A block that your handler should call with either the value of the request parameter, a modified URL request object, or NULL to refuse the redirect and return the body of the redirect response.
     */
    public function urlSessionTaskWillPerformHTTPRedirection(URLSession $session, URLSessionTask $task, HTTPURLResponse $response, URLRequest $request, Closure $completionHandler): void;

    /**
     * Requests credentials from the delegate in response to an authentication request from the remote server.
     * @param URLSession $session The session containing the task whose request requires authentication.
     * @param URLSessionTask $task The task whose request requires authentication.
     * @param URLAuthenticationChallenge $challenge An object that contains the request for authentication.
     * @param Closure(URLSessionAuthChallengeDisposition, ?URLCredential):void $completionHandler A handler that your delegate method must call. Its parameters are:
     * disposition—One of several constants that describes how the challenge should be handled {@see URLSessionAuthChallengeDisposition}.
     * credential—The credential that should be used for authentication if disposition is {@see URLSessionAuthChallengeDisposition::useCredential}; otherwise, NULL.
     */
    public function urlSessionTaskDidReceiveChallenge(URLSession $session, URLSessionTask $task, URLAuthenticationChallenge $challenge, Closure $completionHandler): void;
}
