<?php

namespace Sabatier\Foundation\Networking;

use Closure;

/**
 * A protocol that defines methods that URL session instances call on their delegates to handle task-level events specific to data and upload tasks.
 */
interface URLSessionDataDelegate extends URLSessionTaskDelegate
{
    /**
     * Tells the delegate that the data task received the initial reply (headers) from the server.
     *
     * @param URLSession $session The session containing the data task that received an initial reply.
     * @param URLSessionDataTask $dataTask The data task that received an initial reply.
     * @param URLResponse $response A URL response object populated with headers.
     * @param Closure(URLSessionResponseDisposition): void $completionHandler A completion handler that your code calls to continue a transfer, passing a {@see URLSessionResponseDisposition} constant to indicate whether the transfer should continue as a data task or should become a download task.
     */
    public function urlSessionDataTaskDidReceiveResponse(URLSession $session, URLSessionDataTask $dataTask, URLResponse $response, Closure $completionHandler): void;

    /**
     * Asks the delegate whether the data (or upload) task should store the response in the cache.
     *
     * @param URLSession $session The session containing the data (or upload) task.
     * @param URLSessionDataTask $dataTask The data (or upload) task.
     * @param CachedURLResponse $proposedResponse The default caching behavior. This behavior is determined based on the current caching policy and the values of certain received headers, such as the Pragma and Cache-Control headers.
     * @param Closure(CachedURLResponse|null): void $completionHandler A block that your handler must call, providing either the original proposed response, a modified version of that response, or NULL to prevent caching the response. If your delegate implements this method, it must call this completion handler; otherwise, your app leaks memory.
     */
    public function urlSessionDataTaskWillCacheResponse(URLSession $session, URLSessionDataTask $dataTask, CachedURLResponse $proposedResponse, Closure $completionHandler): void;

    /**
     * Tells the delegate that the data task has received some of the expected data.
     *
     * @param URLSession $session The session containing the data task that provided data.
     * @param URLSessionDataTask $task The data task that provided data.
     * @param string $data A data object containing the transferred data.
     */
    public function urlSessionDataTaskReceiveData(URLSession $session, URLSessionDataTask $task, string $data): void;
}
