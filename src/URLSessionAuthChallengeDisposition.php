<?php

namespace Sabatier\Foundation;

/**
 * Class URLSessionAuthChallengeDisposition
 * Constants passed by session or task delegates to the provided continuation block in response to an authentication challenge.
 * @package Sabatier\Foundation
 */
enum URLSessionAuthChallengeDisposition: int
{
    /** Use the specified credential, which may be nil. */
    case useCredential = 0;
    /** Use the default handling for the challenge as though this delegate method were not implemented. The provided credential parameter is ignored. */
    case performDefaultHandling = 1;
    /** Cancel the entire request. The provided credential parameter is ignored. */
    case cancelAuthenticationChallenge = 2;
    /** Reject this challenge, and call the authentication delegate method again with the next authentication protection space. The provided credential parameter is ignored.
     * The {@see rejectProtectionSpace} disposition is only appropriate in fairly unusual situations. For example, a Windows server might use both {@see URLAuthenticationMethodNegotiate} and {@see URLAuthenticationMethodNTLM}. If your app can only handle NTLM, you would want to reject the Negotiate challenge, in order to then receive the queued NTLM challenge.
     * However, most apps won't face this scenario, and if you cannot provide a credential for a certain authentication method, you should usually fall back to the {@see performDefaultHandling} disposition instead.
     */
    case rejectProtectionSpace = 3;
}