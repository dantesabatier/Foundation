<?php

namespace Sabatier\Foundation\Networking;

use Exception;
use Override;
use Sabatier\Foundation\ObjectClass;
use function Sabatier\Foundation\fatal_error;

/** @internal */
final class URLSessionAuthenticationChallengeSender extends ObjectClass implements URLAuthenticationChallengeSender
{
    /**
     * @throws Exception
     */
    #[Override]
    public function cancel(URLAuthenticationChallenge $challenge): void
    {
        fatal_error("Foundation only supports URLSession; for challenges coming from URLSession, please implement the appropriate URLSessionTaskDelegate methods rather than using the sender argument.");
    }

    /**
     * @throws Exception
     */
    #[Override]
    public function continueWithoutCredential(URLAuthenticationChallenge $challenge): void
    {
        fatal_error("Foundation only supports URLSession; for challenges coming from URLSession, please implement the appropriate URLSessionTaskDelegate methods rather than using the sender argument.");
    }

    /**
     * @throws Exception
     */
    #[Override]
    public function use(URLCredential $credential, URLAuthenticationChallenge $challenge): void
    {
        fatal_error("Foundation only supports URLSession; for challenges coming from URLSession, please implement the appropriate URLSessionTaskDelegate methods rather than using the sender argument.");
    }

    /**
     * @throws Exception
     */
    #[Override]
    public function performDefaultHandling(URLAuthenticationChallenge $challenge): void
    {
        fatal_error("Foundation only supports URLSession; for challenges coming from URLSession, please implement the appropriate URLSessionTaskDelegate methods rather than using the sender argument.");
    }

    /**
     * @throws Exception
     */
    #[Override]
    public function rejectProtectionSpaceAndContinue(URLAuthenticationChallenge $challenge): void
    {
        fatal_error("Foundation only supports URLSession; for challenges coming from URLSession, please implement the appropriate URLSessionTaskDelegate methods rather than using the sender argument.");
    }
}
