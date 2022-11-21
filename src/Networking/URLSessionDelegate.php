<?php

namespace Sabatier\Foundation\Networking;

use Closure;
use Sabatier\Foundation\Error;

/**
 * A protocol that defines methods that URL session instances call on their delegates to handle session-level events, like session life cycle changes.
 */
interface URLSessionDelegate
{
    /**
     * Tells the URL session that the session has been invalidated. If you invalidate a session by calling its {@see URLSession::finishTasksAndInvalidate()} method, the session waits until after the final task in the session finishes or fails before calling this delegate method. If you call the {@see URLSession::invalidateAndCancel()} method, the session calls this delegate method immediately.
     * @param URLSession $session The session object that was invalidated.
     * @param Error|null $error The error that caused invalidation, or nil if the invalidation was explicit.
     */
    public function urlSessionDidBecomeInvalidWithError(URLSession $session, ?Error $error = null): void;

    /**
     * Requests credentials from the delegate in response to a session-level authentication request from the remote server.
     * @param URLSession $session The session containing the task that requested authentication.
     * @param URLAuthenticationChallenge $challenge An object that contains the request for authentication.
     * @param Closure(URLSessionAuthChallengeDisposition, ?URLCredential):void $completionHandler A handler that your delegate method must call. Its parameters are:
     * disposition—One of several constants that describes how the challenge should be handled {@see URLSessionAuthChallengeDisposition}.
     * credential—The credential that should be used for authentication if disposition is {@see URLSessionAuthChallengeDisposition::useCredential}; otherwise, NULL.
     */
    public function urlSessionDidReceiveChallenge(URLSession $session, URLAuthenticationChallenge $challenge, Closure $completionHandler): void;
}
