<?php

namespace Sabatier\Foundation\Networking;

use Sabatier\Foundation\Error;

/**
 * The interface used by {@see URLProtocol} subclasses to communicate with the URL Loading System.
 *
 * Don't implement this protocol in your application. Instead, your URLProtocol subclass calls methods of this protocol on its own {@see URLProtocol::$client} property.
 */
interface URLProtocolClient
{
    /**
     * Tells the client that the protocol implementation has created a response object for the request.
     *
     * The implementation should use the provided cache storage policy to determine whether to store the response in a cache.
     * @param URLProtocol $protocol The URL protocol object sending the message.
     * @param URLResponse $response The newly available response object.
     * @param URLCacheStoragePolicy $policy The cache storage policy for the response.
     */
    public function urlProtocolDidReceiveCacheStoragePolicy(URLProtocol $protocol, URLResponse $response, URLCacheStoragePolicy $policy): void;

    /**
     * Tells the client that the protocol implementation has been redirected.
     *
     * @param URLProtocol $protocol The URL protocol object sending the message.
     * @param URLRequest $request The new request that the original request was redirected to.
     * @param URLResponse $response The response from the original request that caused the redirect.
     */
    public function urlProtocolWasRedirectedToRedirectResponse(URLProtocol $protocol, URLRequest $request, URLResponse $response): void;

    /**
     * Tells the client that a cached response is valid.
     *
     * @param URLProtocol $protocol The URL protocol object sending the message.
     * @param CachedURLResponse $cachedResponse The cached response whose validity is being communicated.
     */
    public function urlProtocolCachedResponseIsValid(URLProtocol $protocol, CachedURLResponse $cachedResponse): void;

    /**
     * Tells the client that an authentication challenge has been canceled.
     *
     * @param URLProtocol $protocol The URL protocol object sending the message.
     * @param URLAuthenticationChallenge $challenge The authentication challenge that was canceled.
     */
    public function urlProtocolDidCancel(URLProtocol $protocol, URLAuthenticationChallenge $challenge): void;

    /**
     * Tells the client that the URL Loading System received an authentication challenge.
     *
     * The protocol client guarantees that it will answer the request on the same thread that called this method. The client may add a default credential to the challenge it issues to the connection delegate, if protocol did not provide one.
     * @param URLProtocol $protocol The URL protocol object sending the message.
     * @param URLAuthenticationChallenge $authenticationChallenge The authentication challenge that has been received.
     */
    public function urlProtocolDidReceive(URLProtocol $protocol, URLAuthenticationChallenge $authenticationChallenge): void;

    /**
     * Tells the client that the load request failed due to an error.
     *
     * @param URLProtocol $protocol The URL protocol object sending the message.
     * @param Error $error The error that caused the failure of the load request.
     */
    public function urlProtocolDidFailWithError(URLProtocol $protocol, Error $error): void;

    /**
     * Tells the client that the protocol implementation has loaded some data.
     *
     * The data object must contain only new data loaded since the previous invocation of this method.
     * @param URLProtocol $protocol The URL protocol object sending the message.
     * @param string $data The data being made available.
     */
    public function urlProtocolDidLoad(URLProtocol $protocol, string $data): void;

    /**
     * Tells the client that the protocol implementation has finished loading.
     *
     * @param URLProtocol $protocol The URL protocol object sending the message.
     */
    public function urlProtocolDidFinishLoading(URLProtocol $protocol): void;
}
