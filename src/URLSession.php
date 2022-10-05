<?php

/**
 * @author Dante Sabatier <dantesabatier@me.com>
 * @version 1.0
 * @package Sabatier\Foundation
 */

namespace Sabatier\Foundation;

use Closure;
use CurlMultiHandle;

/**
 * Class URLSession
 * An object that coordinates a group of related, network data-transfer tasks.
 * @package Sabatier\Foundation
 */
final class URLSession
{
    /** @internal */
    public static int $debugLevel = 0;
    private static ?URLSession $shared = null;
    private readonly CurlMultiHandle $mh;
    /** @var ArrayClass<URLSessionTask> */
    public readonly ArrayClass $allTasks;

    /**
     * Creates a session with the specified session configuration.
     * @param URLSessionConfiguration $configuration A configuration object that specifies certain behaviors, such as caching policies, timeouts, proxies, pipelining, TLS versions to support, cookie policies, credential storage, and so on.
     * @param URLSessionDelegate|null $delegate A session delegate object that handles requests for authentication and other session-related events.
     * This delegate object is responsible for handling authentication challenges, for making caching decisions, and for handling other session-related events. If nil, the class should be used only with methods that take completion handlers.
     * @param OperationQueue $delegateQueue An operation queue for scheduling the delegate calls and completion handlers. The queue should be a serial queue, in order to ensure the correct ordering of callbacks. If nil, the session creates a serial operation queue for performing all delegate method calls and completion handler calls.
     */
    public function __construct(public readonly URLSessionConfiguration $configuration, public readonly ?URLSessionDelegate $delegate = null, public readonly OperationQueue $delegateQueue = new OperationQueue())
    {
        $this->mh = curl_multi_init();
        $this->allTasks = new ArrayClass();
    }

    /**
     * The shared singleton session object.
     */
    public static function shared(): URLSession
    {
        if (self::$shared === null) {
            self::$shared = new self(URLSessionConfiguration::default());
        }
        return self::$shared;
    }

    /**
     * Creates a task that retrieves the contents of a URL based on the specified URL request object.
     * @param URLRequest $request A URL request object that provides request-specific information such as the URL, cache policy, request type, and body data or body stream.
     * @param Closure(string|null, URLResponse|null, Error|null): void $completion The completion handler to call when the load request is complete.
     * @return URLSessionDataTask The new session data task.
     */
    public function dataTaskWithRequest(URLRequest $request, Closure $completion): URLSessionDataTask
    {
        $task = URLSessionDataTask::dataTaskWithRequest($request, function (?string $data, ?URLResponse $response, ?Error $error) use ($request, $completion): void {
            $task = $this->allTasks->first(fn(URLSessionTask $task): bool => $task->request === $request);
            if ($task instanceof URLSessionDataTask) {
                $this->remove($task);
            }
            $completion($data, $response, $error);
        });
        $this->add($task);
        return $task;
    }

    /**
     * Creates a task that retrieves the contents of the specified URL, then calls a handler upon completion.
     * @param URL $url The URL to be retrieved.
     * @param Closure(string|null, URLResponse|null, Error|null): void $completion The completion handler to call when the load request is complete.
     * @return URLSessionDataTask The new session data task.
     */
    public function dataTaskWithURL(URL $url, Closure $completion): URLSessionDataTask
    {
        return $this->dataTaskWithRequest(new URLRequest($url), $completion);
    }

    /**
     * Creates a download task that retrieves the contents of a URL based on the specified URL request object and saves the results to a file.
     * @param URLRequest $request A URL request object that provides the URL, cache policy, request type, body data or body stream, and so on.
     * @param Closure(URL|null, URLResponse|null, Error|null): void $completion The completion handler to call when the load request is complete.
     */
    public function downloadTaskWithRequest(URLRequest $request, Closure $completion): URLSessionDownloadTask
    {
        $task = URLSessionDownloadTask::downloadTaskWithRequest($request, function (?URL $location, ?URLResponse $response, ?Error $error) use ($request, $completion): void {
            $task = $this->allTasks->first(fn(URLSessionTask $task): bool => $task->request === $request);
            if ($task instanceof URLSessionDownloadTask) {
                $this->remove($task);
                $delegate = $this->delegate;
                if ($delegate instanceof URLSessionDownloadDelegate) {
                    $this->delegateQueue->addOperationWithBlock(function () use ($delegate, $task, $location) {
                        $delegate->urlSessionDownloadTaskDidFinishDownloadingToURL($this, $task, $location);
                    });
                }
            }
            $completion($location, $response, $error);
        });
        $this->add($task);
        return $task;
    }

    /**
     * Creates a download task that retrieves the contents of the specified URL, saves the results to a file, and calls a handler upon completion.
     * @param URL $url The URL to download.
     * @param Closure(URL|null, URLResponse|null, Error|null): void $completion The completion handler to call when the load request is complete.
     */
    public function downloadTaskWithURL(URL $url, Closure $completion): URLSessionDownloadTask
    {
        return $this->downloadTaskWithRequest(new URLRequest($url), $completion);
    }

    /**
     * Creates a task that performs an HTTP request for uploading the specified file, then calls a handler upon completion.
     * @param URLRequest $request A URL request object that provides the URL, cache policy, request type, and so on. The body stream and body data in this request object are ignored.
     * @param URL $fileUrl The URL of the file to upload.
     * @param Closure(string|null, URLResponse|null, Error|null): void $completion The completion handler to call when the load request is complete.
     */
    public function uploadTaskWithRequest(URLRequest $request, URL $fileUrl, Closure $completion): URLSessionUploadTask
    {
        $task = URLSessionUploadTask::uploadTaskWithRequest($request, $fileUrl, function (?string $data, ?URLResponse $response, ?Error $error) use ($request, $completion): void {
            $task = $this->allTasks->first(fn(URLSessionTask $task): bool => $task->request === $request);
            if ($task instanceof URLSessionUploadTask) {
                $this->remove($task);
            }
            $completion($data, $response, $error);
        });
        $this->add($task);
        return $task;
    }

    private function add(URLSessionTask $task): void
    {
        $task->session = $this;
        $this->allTasks->append($task);
        $this->allTasks->sort(fn(URLSessionTask $e0, URLSessionTask $e1): int => (ComparisonResult::orderedAscending->value * ($e0->priority <=> $e1->priority)));
        curl_multi_add_handle($this->mh, $task->ch);
    }

    private function remove(URLSessionTask $task): void
    {
        $this->allTasks->remove($task);
        curl_multi_remove_handle($this->mh, $task->ch);
        curl_close($task->ch);
    }

    /** @internal */
    public function execute(URLSessionTask $task, Closure $completion): void
    {
        $ch = $task->ch;
        $mh = $this->mh;
        do {
            curl_multi_exec($mh, $running);
            curl_multi_select($mh);
            /** @noinspection PhpExpressionResultUnusedInspection */
            curl_multi_info_read($mh); // @phpstan-ignore-line
        } while ($running);
        $data = curl_multi_getcontent($ch);
        $errno = curl_errno($ch);
        $error = ($errno != CURLE_OK) ? new Error(URLErrorDomain, $errno, new Dictionary([LocalizedFailureReasonErrorKey => curl_error($ch)])) : null;
        $completion($data, curl_getinfo($ch, CURLINFO_HTTP_CODE), $error);
    }

    /**
     * Asynchronously calls a completion callback with all data, upload, and download tasks in a session.
     * @param Closure(ArrayClass<URLSessionDataTask>, ArrayClass<URLSessionUploadTask>, ArrayClass<URLSessionDownloadTask>): void $completionHandler The completion handler to call with the list of tasks.
     */
    public function getTasksWithCompletionHandler(Closure $completionHandler): void
    {
        $this->getAllTasks(function (ArrayClass $tasks) use ($completionHandler): void {
            /** @var ArrayClass<URLSessionDataTask> $dataTasks */
            $dataTasks = new ArrayClass();
            /** @var ArrayClass<URLSessionUploadTask> $uploadTasks */
            $uploadTasks = new ArrayClass();
            /** @var ArrayClass<URLSessionDownloadTask> $downloadTasks */
            $downloadTasks = new ArrayClass();
            foreach ($tasks as $task) {
                if ($task instanceof URLSessionUploadTask) {
                    $uploadTasks[] = $task;
                } elseif ($task instanceof URLSessionDownloadTask) {
                    $downloadTasks[] = $task;
                } elseif ($task instanceof URLSessionDataTask) {
                    $dataTasks[] = $task;
                }
            }
            $completionHandler($dataTasks, $uploadTasks, $downloadTasks);
        });
    }

    /**
     * @return array{dataTasks: ArrayClass<URLSessionDataTask>, uploadTasks: ArrayClass<URLSessionUploadTask>, downloadTasks: ArrayClass<URLSessionDownloadTask>}
     */
    public function tasks(): array
    {
        $tasks = [];
        $this->getTasksWithCompletionHandler(function (ArrayClass $dataTasks, ArrayClass $uploadTasks, ArrayClass $downloadTasks) use (&$tasks): void {
            $tasks = ['dataTasks' => $dataTasks, 'uploadTasks' => $uploadTasks, 'downloadTasks' => $downloadTasks];
        });
        return $tasks;
    }

    /**
     * Asynchronously calls a completion callback with all tasks in a session
     * @param Closure(ArrayClass<URLSessionTask>): void $completionHandler The completion handler to call with the list of tasks.
     */
    public function getAllTasks(Closure $completionHandler): void
    {
        $completionHandler($this->allTasks);
    }

    /**
     * Cancels all outstanding tasks and then invalidates the session.
     */
    public function invalidateAndCancel(): void
    {
        foreach ($this->allTasks as $task) {
            $task->cancel();
        }
        $delegate = $this->delegate;
        if ($delegate instanceof URLSessionDelegate) {
            $this->delegateQueue->addOperationWithBlock(function () use ($delegate) {
                $delegate->urlSessionDidBecomeInvalidWithError($this, new Error(CocoaErrorDomain, UserCancelledError));
            });
        }
    }

    /**
     * Empties all cookies, caches and credential stores, removes disk files, flushes in-progress downloads to disk, and ensures that future requests occur on a new socket.
     * @param Closure $completion The completion handler to call when the reset operation is complete.
     */
    public function reset(Closure $completion): void
    {
        $completion();
    }
}
