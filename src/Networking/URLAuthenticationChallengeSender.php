<?php

declare(strict_types=1);

namespace Sabatier\Foundation\Networking;

/**
 * The URLAuthenticationChallengeSender protocol represents the interface that the sender of an authentication challenge must implement.
 */
interface URLAuthenticationChallengeSender
{
    /**
     * Cancels a given authentication challenge.
     * @param URLAuthenticationChallenge $challenge The authentication challenge to cancel.
     */
    public function cancel(URLAuthenticationChallenge $challenge): void;

    /**
     * Attempt to continue downloading a request without providing a credential for a given challenge.
     * This method has no effect if it is called with an authentication challenge that has already been handled.
     * @param URLAuthenticationChallenge $challenge A challenge without authentication credentials.
     */
    public function continueWithoutCredential(URLAuthenticationChallenge $challenge): void;

    /**
     * Attempt to use a given credential for a given authentication challenge.
     * This method has no effect if it is called with an authentication challenge that has already been handled.
     * @param URLCredential $credential The credential to use for authentication.
     * @param URLAuthenticationChallenge $challenge The challenge for which to use credential.
     */
    public function use(URLCredential $credential, URLAuthenticationChallenge $challenge): void;

    /**
     * Causes the system-provided default behavior to be used.
     * @param URLAuthenticationChallenge $challenge The challenge for which the default behavior should be used.
     */
    public function performDefaultHandling(URLAuthenticationChallenge $challenge): void;

    /**
     * Rejects the currently supplied protection space.
     * @param URLAuthenticationChallenge $challenge The challenge that should be rejected.
     */
    public function rejectProtectionSpaceAndContinue(URLAuthenticationChallenge $challenge): void;
}
