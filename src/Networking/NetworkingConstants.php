<?php

namespace Sabatier\Foundation\Networking;

/** The response length cannot be determined in advance of receiving the data from the server. For example, URLResponseUnknownLength is returned when the server HTTP response does not include a Content-Length header. */
const URLResponseUnknownLength = -1;
/** @var int The total size of the transfer cannot be determined. */
const URLSessionTransferSizeUnknown = -1.0;
/** @var string Use client certificate authentication for this protection space. */
const URLAuthenticationMethodClientCertificate = "URLAuthenticationMethodClientCertificate";
/** @var string Negotiate whether to use Kerberos or NTLM authentication for this protection space. */
const URLAuthenticationMethodNegotiate = "URLAuthenticationMethodNegotiate";
/** @var string Use NTLM authentication for this protection space. */
const URLAuthenticationMethodNTLM = "URLAuthenticationMethodNTLM";
/** @var string Perform server trust authentication (certificate validation) for this protection space. */
const URLAuthenticationMethodServerTrust = "URLAuthenticationMethodServerTrust";
/** @var string Use the default authentication method for a protocol. */
const URLAuthenticationMethodDefault = "URLAuthenticationMethodDefault";
/** @var string Use HTML form authentication for this protection space. */
const URLAuthenticationMethodHTMLForm = "URLAuthenticationMethodHTMLForm";
/** @var string Use HTTP basic authentication for this protection space. */
const URLAuthenticationMethodHTTPBasic = "URLAuthenticationMethodHTTPBasic";
/** @var string Use HTTP digest authentication for this protection space. */
const URLAuthenticationMethodHTTPDigest = "URLAuthenticationMethodHTTPDigest";
const URLAuthenticationMethodHTTPBearer = "URLAuthenticationMethodHTTPBearer";
/** @var string The protocol type for HTTP. */
const URLProtectionSpaceHTTP = "URLProtectionSpaceHTTP";
/** @var string The protocol type for HTTPS. */
const URLProtectionSpaceHTTPS = "URLProtectionSpaceHTTPS";
/** @var string The protocol type for FTP. */
const URLProtectionSpaceFTP = "URLProtectionSpaceFTP";
/** @var string The corresponding value is an Number object representing a Boolean value that indicates whether credentials which contain the URLCredentialPersistence.synchronizable attribute should be removed. If the key is missing or the value is @NO, then no attempt will be made to remove such a credential. */
const URLCredentialStorageRemoveSynchronizableCredentials = "URLCredentialStorageRemoveSynchronizableCredentials";
