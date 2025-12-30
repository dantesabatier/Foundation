<?php

namespace Sabatier\Foundation\Networking;

use Sabatier\Foundation\Error;
use Sabatier\Foundation\ObjectClass;

/**
 * A challenge from a server requiring authentication from the client.
 */
final class URLAuthenticationChallenge extends ObjectClass
{
    /**
     * Initializes an authentication challenge from parameters you provide.
     * @param URLProtectionSpace $protectionSpace The protection space for the authentication challenge. This provides additional information about the authentication request, such as the host, port, authentication realm, and so on.
     * @param URLCredential|null $proposedCredential The proposed credential, or nil.
     * @param int $previousFailureCount The total number of previous failures for this request, including failures for other protection spaces.
     * @param URLResponse|null $failureResponse An instance of URLResponse containing the server response that caused you to generate an authentication challenge, or nil if no response object is applicable to the challenge.
     * @param Error|null $error An Error instance describing the authentication failure, or nil if it is not applicable to the challenge.
     * @param URLAuthenticationChallengeSender|null $sender The object that initiated the authentication challenge (typically, the object that called this method).
     */
    public function __construct(public readonly URLProtectionSpace $protectionSpace, public readonly ?URLCredential $proposedCredential = null, public readonly int $previousFailureCount = 0, public readonly ?URLResponse $failureResponse = null, public readonly ?Error $error = null, public readonly ?URLAuthenticationChallengeSender $sender = null)
    {
    }
}
