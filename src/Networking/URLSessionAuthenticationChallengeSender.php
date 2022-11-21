<?php

namespace Sabatier\Foundation\Networking;

use Exception;
use Sabatier\Foundation\ObjectClass;
use function Sabatier\Foundation\fatal_error;

/** @internal */
class URLSessionAuthenticationChallengeSender extends ObjectClass implements URLAuthenticationChallengeSender
{
    /**
     * @throws Exception
     */
    public function cancel(URLAuthenticationChallenge $challenge): void
    {
        fatal_error("Foundation only supports URLSession; for challenges coming from URLSession, please implement the appropriate URLSessionTaskDelegate methods rather than using the sender argument.");
    }

    /**
     * @throws Exception
     */
    public function continueWithoutCredential(URLAuthenticationChallenge $challenge): void
    {
        fatal_error("Foundation only supports URLSession; for challenges coming from URLSession, please implement the appropriate URLSessionTaskDelegate methods rather than using the sender argument.");
    }

    /**
     * @throws Exception
     */
    public function use(URLCredential $credential, URLAuthenticationChallenge $challenge): void
    {
        fatal_error("Foundation only supports URLSession; for challenges coming from URLSession, please implement the appropriate URLSessionTaskDelegate methods rather than using the sender argument.");
    }

    /**
     * @throws Exception
     */
    public function performDefaultHandling(URLAuthenticationChallenge $challenge): void
    {
        fatal_error("Foundation only supports URLSession; for challenges coming from URLSession, please implement the appropriate URLSessionTaskDelegate methods rather than using the sender argument.");
    }

    /**
     * @throws Exception
     */
    public function rejectProtectionSpaceAndContinue(URLAuthenticationChallenge $challenge): void
    {
        fatal_error("Foundation only supports URLSession; for challenges coming from URLSession, please implement the appropriate URLSessionTaskDelegate methods rather than using the sender argument.");
    }
}
