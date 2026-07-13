<?php

declare(strict_types=1);

namespace Sabatier\Foundation\Networking;

use Closure;
use Override;
use Sabatier\Foundation\ArrayClass;
use Sabatier\Foundation\Dictionary;
use Sabatier\Foundation\Error;
use Sabatier\Foundation\OperationQueue;
use Sabatier\Foundation\URL;
use function Sabatier\Foundation\fatal_error;
use function Sabatier\Foundation\unsupported;

/**
 * An object that coordinates a group of related, network data-transfer tasks.
 * @psalm-type DataCompletionHandler Closure(string|null, URLResponse|null, Error|null): void
 * @psalm-type DownloadCompletionHandler Closure(URL|null, URLResponse|null, Error|null): void
 */
final class URLSession implements URLSessionProtocol
{
    private static ?URLSession $shared = null;
    private MultiHandle $multiHandle {
        get => $this->multiHandle ??= new MultiHandle($this->configuration);
    }
    /** @internal */
    private(set) TaskRegistry $taskRegistry {
        get => $this->taskRegistry ??= new TaskRegistry();
    }
    private(set) ?string $identifier = null;
    private bool $invalidated = false;
    private int $nextTaskIdentifier = 1;

    /**
     * Creates a session with the specified session configuration.
     * @param URLSessionConfiguration $configuration A configuration object that specifies certain behaviors, such as caching policies, timeouts, proxies, pipelining, TLS versions to support, cookie policies, credential storage, and so on.
     * @param URLSessionDelegate|null $delegate A session delegate object that handles requests for authentication and other session-related events.
     * This delegate object is responsible for handling authentication challenges, for making caching decisions and for handling other session-related events. If null, the class should be used only with methods that take completion handlers.
     * @param OperationQueue $delegateQueue An operation queue for scheduling the delegate calls and completion handlers. The queue should be a serial queue to ensure the correct ordering of callbacks. If null, the session creates a serial operation queue for performing all delegate method calls and completion handler calls.
     */
    public function __construct(public readonly URLSessionConfiguration $configuration, public readonly ?URLSessionDelegate $delegate = null, public readonly OperationQueue $delegateQueue = new OperationQueue())
    {
        self::registerProtocols();
    }

    private static function registerProtocols(): void
    {
        URLProtocol::registerClass(HTTPURLProtocol::class);
        URLProtocol::registerClass(FTPURLProtocol::class);
        URLProtocol::registerClass(DataURLProtocol::class);
        URLProtocol::registerClass(WebSocketURLProtocol::class);
    }

    private function createNextTaskIdentifier(): int
    {
        $this->nextTaskIdentifier += 1;
        return $this->nextTaskIdentifier;
    }

    /**
     * The shared singleton session object.
     */
    public static function shared(): URLSession
    {
        if (self::$shared === null) {
            $configuration = URLSessionConfiguration::default();
            $configuration->protocolClasses = URLProtocol::getProtocols();
            self::$shared = new self($configuration);
        }
        return self::$shared;
    }

    /**
     * @internal
     */
    public function createConfiguredRequest(URLRequest $request): URLRequest
    {
        return $this->configuration->configure($request);
    }

    private function dataTask(URLRequest $request, TaskRegistryBehaviour $behaviour): URLSessionDataTask
    {
        !$this->invalidated ?: fatal_error("Session invalidated");
        $task = new URLSessionDataTask($this, $this->createConfiguredRequest($request), $this->createNextTaskIdentifier());
        $this->taskRegistry->add($task, $behaviour);
        return $task;
    }

    private function uploadTask(URLRequest $request, TaskBody $body, TaskRegistryBehaviour $behaviour): URLSessionUploadTask
    {
        !$this->invalidated ?: fatal_error("Session invalidated");
        $task = new URLSessionUploadTask($this, $this->createConfiguredRequest($request), $this->createNextTaskIdentifier(), $body);
        $this->taskRegistry->add($task, $behaviour);
        return $task;
    }

    private function downloadTask(URLRequest $request, TaskRegistryBehaviour $behaviour): URLSessionDownloadTask
    {
        !$this->invalidated ?: fatal_error("Session invalidated");
        $task = new URLSessionDownloadTask($this, $this->createConfiguredRequest($request), $this->createNextTaskIdentifier());
        $this->taskRegistry->add($task, $behaviour);
        return $task;
    }

    private function webSocketTask(URLRequest $request, TaskRegistryBehaviour $behaviour): URLSessionWebSocketTask
    {
        !$this->invalidated ?: fatal_error("Session invalidated");
        $task = new URLSessionWebSocketTask($this, $this->createConfiguredRequest($request), $this->createNextTaskIdentifier());
        $this->taskRegistry->add($task, $behaviour);
        return $task;
    }

    /**
     * Creates a task that retrieves the contents of the specified URL, then calls a handler upon completion.
     *
     * @param URL $url The URL to be retrieved.
     * @param DataCompletionHandler|null $completionHandler The completion handler to call when the load request is complete. This handler is executed on the delegate queue. If you pass null, only the session delegate methods are called when the task completes, making this method equivalent to the {@see dataTaskWithRequest()} method.
     * @return URLSessionDataTask The new session data task.
     */
    public function dataTaskWithURL(URL $url, ?Closure $completionHandler = null): URLSessionDataTask
    {
        return $this->dataTask(new URLRequest($url), $completionHandler ? TaskRegistryBehaviour::dataCompletionHandler($completionHandler) : TaskRegistryBehaviour::callDelegate());
    }

    /**
     * Creates a task that retrieves the contents of a URL based on the specified URL request object.
     *
     * @param URLRequest $request A URL request object that provides request-specific information such as the URL, cache policy, request type, and body data or body stream.
     * @param DataCompletionHandler|null $completionHandler The completion handler to call when the load request is complete.
     * @return URLSessionDataTask The new session data task.
     */
    public function dataTaskWithRequest(URLRequest $request, ?Closure $completionHandler = null): URLSessionDataTask
    {
        return $this->dataTask($request, $completionHandler ? TaskRegistryBehaviour::dataCompletionHandler($completionHandler) : TaskRegistryBehaviour::callDelegate());
    }

    /**
     * Creates a download task that retrieves the contents of the specified URL, saves the results to a file and calls a handler upon completion.
     *
     * @param URL $url The URL to download.
     * @param DownloadCompletionHandler|null $completionHandler The completion handler to call when the load request is complete. This handler is executed on the delegate queue.
     * @return URLSessionDownloadTask The new session download task.
     */
    public function downloadTaskWithURL(URL $url, ?Closure $completionHandler = null): URLSessionDownloadTask
    {
        return $this->downloadTask(new URLRequest($url), $completionHandler ? TaskRegistryBehaviour::downloadCompletionHandler($completionHandler) : TaskRegistryBehaviour::callDelegate());
    }

    /**
     * Creates a download task that retrieves the contents of a URL based on the specified URL request object and saves the results to a file.
     * @param URLRequest $request A URL request object that provides the URL, cache policy, request type, body data or body stream, and so on.
     * @param DownloadCompletionHandler|null $completionHandler The completion handler to call when the load request is complete.
     * @return URLSessionDownloadTask The new session download task.
     */
    public function downloadTaskWithRequest(URLRequest $request, ?Closure $completionHandler = null): URLSessionDownloadTask
    {
        return $this->downloadTask($request, $completionHandler ? TaskRegistryBehaviour::downloadCompletionHandler($completionHandler) : TaskRegistryBehaviour::callDelegate());
    }

    /**
     * Creates a task that performs an HTTP request for uploading the specified file, then calls a handler upon completion.
     * @param URLRequest $request A URL request object that provides the URL, cache policy, request type, and so on. The body stream and body data in this request object are ignored.
     * @param URL $fileURL The URL of the file to upload.
     * @param DataCompletionHandler|null $completionHandler The completion handler to call when the load request is complete.
     */
    public function uploadTaskWithRequest(URLRequest $request, URL $fileURL, ?Closure $completionHandler = null): URLSessionUploadTask
    {
        return $this->uploadTask($request, TaskBody::file($fileURL), $completionHandler ? TaskRegistryBehaviour::dataCompletionHandler($completionHandler) : TaskRegistryBehaviour::callDelegate());
    }

    /**
     * Creates a task that establishes a bidirectional TCP/IP connection to a specified hostname and port.
     *
     * After you create the task, you must start it by calling its {@see URLSessionTask::resume()} method.
     * @param string $hostname The hostname of the connection endpoint.
     * @param int $port The port of the connection endpoint.
     * @return URLSessionStreamTask The new session stream task.
     */
    public function streamTaskWithHostName(/** @noinspection PhpUnusedParameterInspection */ string $hostname, int $port): URLSessionStreamTask
    {
        unsupported($this, __FUNCTION__);
    }

    /**
     * Creates a WebSocket task for the provided URL request.
     *
     * You can modify the request's properties before calling resume on the task. The task uses these properties during the HTTP handshake phase.
     * To add custom protocols, add a header with the key Sec-WebSocket-Protocol, and a comma-separated list of protocols you want to negotiate with the server. The custom HTTP headers provided by the client remain unchanged for the handshake with the server.
     * @param URLRequest $request A URL request that indicates a WebSockets endpoint with which to connect.
     * @return URLSessionWebSocketTask
     */
    public function webSocketTaskWithRequest(URLRequest $request): URLSessionWebSocketTask
    {
        return $this->webSocketTask($request, TaskRegistryBehaviour::callDelegate());
    }

    /**
     * Creates a WebSocket task given a URL and an array of protocols.
     *
     * During the WebSocket handshake, the task uses the provided protocols to negotiate a preferred protocol with the server.
     * @param URL $url The WebSocket URL with which to connect.
     * @param ArrayClass<string> $protocols An array of protocols to negotiate with the server.
     * @return URLSessionWebSocketTask
     */
    public function webSocketTaskWithURL(URL $url, ArrayClass $protocols = new ArrayClass()): URLSessionWebSocketTask
    {
        $request = new URLRequest($url);
        if (!$protocols->isEmpty) {
            $request->setValueForHttpHeaderField($protocols->join(", "), "Sec-WebSocket-Protocol");
        }
        return $this->webSocketTaskWithRequest($request);
    }

    /** @internal */
    #[Override]
    public function add(EasyHandle $handle): void
    {
        $this->multiHandle->add($handle);
    }

    /**
     * @internal
     */
    #[Override]
    public function remove(EasyHandle $handle): void
    {
        $this->multiHandle->remove($handle);
    }

    /** @internal */
    #[Override]
    public function behaviour(URLSessionTask $task): TaskBehaviour
    {
        $behaviour = $this->taskRegistry->behaviour($task);
        /** @psalm-suppress InvalidArgument */
        return match ($behaviour->rawValue) {
            TaskRegistryBehaviourRawValue::callDelegate => $this->delegate instanceof URLSessionDelegate ? TaskBehaviour::taskDelegate($this->delegate) : TaskBehaviour::noDelegate(),
            TaskRegistryBehaviourRawValue::dataCompletionHandler => TaskBehaviour::dataCompletionHandler($behaviour->dataCompletionHandler),
            TaskRegistryBehaviourRawValue::downloadCompletionHandler => TaskBehaviour::downloadCompletionHandler($behaviour->downloadCompletionHandler),
        };
    }

    /**
     * Empties all cookies, caches and credential stores, removes disk files, flushes in-progress downloads to disk and ensures that future requests occur on a new socket.
     * @param Closure(): void $completionHandler The completion handler to call when the reset operation is complete.
     */
    public function reset(Closure $completionHandler): void
    {
        $this->configuration->urlCache?->removeAllCachedResponses();
        if ($storage = $this->configuration->urlCredentialStorage) {
            $storage->allCredentials->forEach(fn(Dictionary $credentialEntry, string $space) => $credentialEntry->forEach(fn(URLCredential $credential) => $storage->remove($credential, $space)));
        }
        $this->flush($completionHandler);
    }

    public function flush(Closure $completionHandler): void
    {
        $this->delegateQueue->addOperationWithBlock(function () use ($completionHandler): void {
            $completionHandler();
        });
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
                    $uploadTasks->append($task);
                } elseif ($task instanceof URLSessionDownloadTask) {
                    $downloadTasks->append($task);
                } elseif ($task instanceof URLSessionDataTask) {
                    $dataTasks->append($task);
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
            $tasks = ["dataTasks" => $dataTasks, "uploadTasks" => $uploadTasks, "downloadTasks" => $downloadTasks];
        });
        $tasks["dataTasks"] ??= new ArrayClass();
        $tasks["uploadTasks"] ??= new ArrayClass();
        $tasks["downloadTasks"] ??= new ArrayClass();
        return $tasks;
    }

    /**
     * Asynchronously calls a completion callback with all tasks in a session
     * @param Closure(ArrayClass<URLSessionTask>): void $completionHandler The completion handler to call with the list of tasks.
     */
    public function getAllTasks(Closure $completionHandler): void
    {
        $this->delegateQueue->addOperationWithBlock(function () use ($completionHandler): void {
            $completionHandler($this->taskRegistry->allTask->filter(fn(URLSessionTask $task): bool => $task->state === URLSessionTaskState::running || $task->isSuspendedAfterResume)->values);
        });
    }

    public function finishTasksAndInvalidate(): void
    {
        $this->invalidated = true;
        $invalidateSessionCallback = function (): void {
            $obj = $this;
            if (!($sessionDelegate = $obj->delegate)) {
                return;
            }
            $obj->delegateQueue->addOperationWithBlock(function () use ($obj, $sessionDelegate): void {
                $sessionDelegate->urlSessionDidBecomeInvalidWithError($obj);
            });
        };
        if (!$this->taskRegistry->isEmpty) {
            $this->taskRegistry->notify($invalidateSessionCallback);
        } else {
            $invalidateSessionCallback();
        }
    }

    /**
     * Cancels all outstanding tasks and then invalidates the session.
     */
    public function invalidateAndCancel(): void
    {
        if ($this === self::$shared) {
            return;
        }
        $this->invalidated = true;
        $this->taskRegistry->allTask->forEach(fn(URLSessionTask $task) => $task->cancel());
        if (!$sessionDelegate = $this->delegate) {
            return;
        }
        $this->delegateQueue->addOperationWithBlock(function () use ($sessionDelegate): void {
            $sessionDelegate->urlSessionDidBecomeInvalidWithError($this);
        });
    }
}
