<?php

namespace Sabatier\Foundation\Networking;

use Closure;
use Sabatier\Foundation\ArrayClass;
use Sabatier\Foundation\Dictionary;
use Sabatier\Foundation\Error;
use Sabatier\Foundation\ObjectClass;
use Sabatier\Foundation\ProcessInfo;
use Sabatier\Foundation\Progress;
use function Sabatier\Foundation\fatal_error;
use const Sabatier\Foundation\CocoaErrorDomain;
use const Sabatier\Foundation\LocalizedDescriptionKey;
use const Sabatier\Foundation\URLErrorDomain;
use const Sabatier\Foundation\URLErrorFailingURLErrorKey;
use const Sabatier\Foundation\URLErrorUnsupportedURL;
use const Sabatier\Foundation\UserCancelledError;

/**
 * A task, like downloading a specific resource, performed in a URL session.
 */
abstract class URLSessionTask extends ObjectClass
{
    /** @var URLSessionTaskState The current state of the task—active, suspended, in the process of being canceled, or completed. */
    public URLSessionTaskState $state = URLSessionTaskState::suspended;
    /** @var float The relative priority at which you'd like a host to handle the task, specified as a floating point value between 0.0 (lowest priority) and 1.0 (highest priority). To provide hints to a host on how to prioritize URL session tasks from your app, specify a priority for each task. Specifying a priority provides only a hint and does not guarantee performance. If you don't specify a priority, a URL session task has a priority of {@see URLSessionTaskPriority::default}, with a value of 0.5. There are three named priorities you can employ, described in {@see URLSessionTaskPriority}. */
    public float $priority = URLSessionTaskPriority::default;
    /** @var Progress A representation of the overall task progress. */
    public Progress $progress;
    /** @var float The number of bytes that the task expects to receive in the response body. This value is determined based on the Content-Length header received from the server. If that header is absent, the value is {@see URLSessionTransferSizeUnknown}. */
    public float $countOfBytesExpectedToReceive = URLSessionTransferSizeUnknown {
        set {
            $this->willChangeValueForKey(__PROPERTY__);
            $this->countOfBytesExpectedToReceive = $value;
            $this->didChangeValueForKey(__PROPERTY__);
            $this->updateProgress();
        }
    }
    /** @var float The number of bytes that the task has received from the server in the response body. */
    public float $countOfBytesReceived = 0.0 {
        set {
            $this->willChangeValueForKey(__PROPERTY__);
            $this->countOfBytesReceived = $value;
            $this->didChangeValueForKey(__PROPERTY__);
            $this->updateProgress();
        }
    }
    /** @var float The number of bytes that the task has sent to the server in the request body. */
    public float $countOfBytesExpectedToSend = URLSessionTransferSizeUnknown {
        set {
            $this->willChangeValueForKey(__PROPERTY__);
            $this->countOfBytesExpectedToSend = $value;
            $this->didChangeValueForKey(__PROPERTY__);
            $this->updateProgress();
        }
    }
    /** @var float The number of bytes that the task has sent to the server in the request body. */
    public float $countOfBytesSent = 0.0 {
        set {
            $this->willChangeValueForKey(__PROPERTY__);
            $this->countOfBytesSent = $value;
            $this->didChangeValueForKey(__PROPERTY__);
            $this->updateProgress();
        }
    }
    /** @var URLRequest|null The URL request object currently being handled by the task. This value is typically the same as the initial request ({@see originalRequest}) except when the server has responded to the initial request with a redirect to a different URL. */
    public ?URLRequest $currentRequest = null;
    /** @var URLRequest|null The original request object passed when the task was created. This value is typically the same as the currently active request ({@see currentRequest}) except when the server has responded to the initial request with a redirect to a different URL. */
    public ?URLRequest $originalRequest = null;
    /** @var URLResponse|null The server's response to the currently active request. This object provides information about the request as provided by the server. This information always includes the original URL. It may also include an expected length, MIME type information, encoding information, a suggested filename, or a combination of these. */
    public ?URLResponse $response = null;
    /** @var string|null An app-provided string value for the current task. The system doesn't interpret this value; use it for whatever purpose you see fit. For example, you could store a description of the task for debugging purposes, or a key to track the task in your own data structures. */
    public ?string $taskDescription = null;
    /** @var int An identifier uniquely identifying the task within a given session. This value is unique only within the context of a single session; tasks in other sessions may have the same taskIdentifier value. */
    public readonly int $taskIdentifier;
    /** @var Error|null An error object that indicates why the task failed. This value is nil if the task is still active or if the transfer completed successfully. */
    public ?Error $error = null;
    /** @var URLSessionTaskDelegate|null A delegate specific to the task. This task-specific delegate receives messages from the task before the session's delegate receives them. This is similar to the behavior of the delegate parameter used by the asynchronous methods in URLSession like bytes(for:delegate:) and data(for:delegate:). */
    public ?URLSessionTaskDelegate $delegate = null;
    /** @var float A best-guess upper bound on the number of bytes the client expects to send. */
    public float $countOfBytesClientExpectsToSend = URLSessionTransferSizeUnknown {
        set {
            $this->willChangeValueForKey(__PROPERTY__);
            $this->countOfBytesClientExpectsToSend = $value;
            $this->didChangeValueForKey(__PROPERTY__);
            $this->updateProgress();
        }
    }
    /** @var float A best-guess upper bound on the number of bytes the client expects to receive. */
    public float $countOfBytesClientExpectsToReceive = URLSessionTransferSizeUnknown {
        set {
            $this->willChangeValueForKey(__PROPERTY__);
            $this->countOfBytesClientExpectsToReceive = $value;
            $this->didChangeValueForKey(__PROPERTY__);
            $this->updateProgress();
        }
    }
    /** @internal */
    public readonly URLSession $session;
    /** @internal */
    public int $suspendCount = 1;
    /** @internal */
    public int $previousFailureCount = 0;
    /** @internal */
    public ?TaskBody $knownBody = null;
    /** @internal */
    public ?URLRequest $authRequest = null;
    /** @internal */
    public ?object $lastCredentialUsedFromStorageDuringAuthentication = null;
    private ProtocolState $protocolStorage;
    private bool $hasTriggeredResume = false;
    /** @internal */
    public bool $isSuspendedAfterResume {
        get => $this->hasTriggeredResume && $this->state === URLSessionTaskState::suspended;
    }
    /** @var string|null class-string<URLProtocol>|null */
    public ?string $protocolClass {
        get {
            /** @var URLRequest $request */
            $request = $this->currentRequest;
            /** @var ArrayClass<class-string<URLProtocol>> $protocolClasses */
            $protocolClasses = $this->session->configuration->protocolClasses ?? URLProtocol::getProtocols() ?? new ArrayClass();
            return URLProtocol::getProtocolClass($protocolClasses, $request);
        }
    }

    /** @internal */
    public function __construct(URLSession $session, URLRequest $request, int $taskIdentifier, ?TaskBody $body = null)
    {
        $this->session = $session;
        $this->originalRequest = $request;
        $this->taskIdentifier = $taskIdentifier;
        $this->currentRequest = $request;
        $this->progress = new Progress();
        $this->progress->cancellationHandler = function (): void {
            $this->cancel();
        };
        if ($body === null) {
            if ($bodyData = $request->httpBody) {
                $body = TaskBody::data($bodyData);
            } elseif ($bodyStream = $request->httpBodyStream) {
                $body = TaskBody::stream($bodyStream);
            }
        }
        $this->knownBody = $body;
        $this->protocolStorage = ProtocolState::toBeCreated();
    }

    private function satisfyProtocolRequest(URLProtocol $protocol): void
    {
        $ps = $this->protocolStorage;
        switch ($ps->rawValue) {
            case ProtocolStateRawValue::toBeCreated:
                $this->protocolStorage = ProtocolState::existing($protocol);
                break;
            case ProtocolStateRawValue::awaitingCacheReply:
                $this->protocolStorage = ProtocolState::existing($protocol);
                /** @var Bag<Closure(URLProtocol|null):void> $bag */
                $bag = $ps->bag;
                foreach ($bag->values as $callback) {
                    $callback($protocol);
                }
                break;
            case ProtocolStateRawValue::existing:
            case ProtocolStateRawValue::invalidated:
                break;
        }
    }

    /**
     * @param Closure(URLProtocol|null): void $callback
     * @internal
     */
    public function getProtocol(Closure $callback): void
    {
        $ps = $this->protocolStorage;
        switch ($ps->rawValue) {
            case ProtocolStateRawValue::toBeCreated:
                if (!($protocolClass = $this->protocolClass)) {
                    $callback(null);
                    return;
                }
                if ($this instanceof URLSessionDataTask && ($cache = $this->session->configuration->urlCache)) {
                    /** @var Bag<Closure(URLProtocol|null):void> $bag */
                    $bag = new Bag();
                    $bag->values[] = $callback;
                    $this->protocolStorage = ProtocolState::awaitingCacheReply($bag);
                    $cache->getCachedResponse($this, function (?CachedURLResponse $cachedResponse) use ($protocolClass): void {
                        $protocol = new $protocolClass($this, $cachedResponse);
                        $this->satisfyProtocolRequest($protocol);
                    });
                } else {
                    $protocol = new $protocolClass($this);
                    $this->protocolStorage = ProtocolState::existing($protocol);
                    $callback($protocol);
                }
                break;
            case ProtocolStateRawValue::awaitingCacheReply:
                /** @var Bag<Closure(URLProtocol|null):void> $bag */
                $bag = $ps->bag;
                $bag->values[] = $callback;
                break;
            case ProtocolStateRawValue::existing:
                $callback($ps->protocol);
                break;
            case ProtocolStateRawValue::invalidated:
                $callback(null);
                break;
        }
    }

    private function updateTaskState(): void
    {
        $this->state = $this->suspendCount === 0 ? URLSessionTaskState::running : URLSessionTaskState::suspended;
    }

    /** @internal */
    public function invalidateProtocol(): void
    {
        $this->protocolStorage = ProtocolState::invalidated();
    }

    /** @internal */
    public function getBody(Closure $completion): void
    {
        if ($body = $this->knownBody) {
            $completion($body);
            return;
        }
        $session = $this->session;
        $delegate = $session->delegate;
        if ($delegate instanceof URLSessionTaskDelegate) {
            $delegate->urlSessionTaskNeedNewBodyStream($session, $this, function (mixed $stream) use ($completion): void {
                if ($stream) {
                    $completion(TaskBody::stream($stream));
                } else {
                    $completion(TaskBody::none());
                }
            });
        } else {
            $completion(TaskBody::none());
        }
    }

    public function updateProgress(): void
    {
        $progress = $this->progress;
        switch ($this->state) {
            case URLSessionTaskState::running:
                /** @noinspection PhpUnhandledExceptionInspection */
                if ($bodyLength = $this->knownBody?->getBodyLength()) {
                    $toBeSent = $bodyLength;
                } elseif ($this->countOfBytesExpectedToSend > 0) {
                    $toBeSent = $this->countOfBytesExpectedToSend;
                } elseif ($this->countOfBytesClientExpectsToSend !== URLSessionTransferSizeUnknown && $this->countOfBytesClientExpectsToSend > 0) {
                    $toBeSent = $this->countOfBytesClientExpectsToSend;
                } else {
                    $toBeSent = null;
                }
                $sent = $this->countOfBytesSent;
                if ($this->countOfBytesExpectedToReceive > 0) {
                    $toBeReceived = $this->countOfBytesExpectedToReceive;
                } elseif ($this->countOfBytesClientExpectsToReceive !== URLSessionTransferSizeUnknown && $this->countOfBytesClientExpectsToSend > 0) {
                    $toBeReceived = $this->countOfBytesClientExpectsToReceive;
                } else {
                    $toBeReceived = null;
                }
                $received = $this->countOfBytesReceived;
                $progress->completedUnitCount = $sent + $received;
                $progress->totalUnitCount = ($toBeSent && $toBeReceived) ? $toBeSent + $toBeReceived : URLSessionTransferSizeUnknown;
                break;
            case URLSessionTaskState::suspended:
            case URLSessionTaskState::canceling:
                break;
            case URLSessionTaskState::completed:
                $total = $progress->totalUnitCount;
                $finalTotal = $total < 0 ? 1 : $total;
                $progress->totalUnitCount = $finalTotal;
                $progress->completedUnitCount = $finalTotal;
                break;
        }
    }

    /**
     * @param string $method
     * @param Challenge $challenge
     * @return Closure(URLSessionTask, URLSessionAuthChallengeDisposition, URLCredential|null): void
     * @internal
     */
    public function authHandler(string $method, Challenge $challenge): Closure
    {
        return function (URLSessionTask $task, URLSessionAuthChallengeDisposition $disposition, ?URLCredential $credential) use ($method, $challenge): void {
            /** @var URLRequest $request */
            $request = $task->originalRequest;
            $username = $credential?->user ?? "";
            $password = $credential?->password ?? "";
            if (!($authorization = match ($method) {
                URLAuthenticationMethodHTTPBasic => base64_encode("$username:$password"),
                URLAuthenticationMethodHTTPDigest => (function () use ($password, $username, $request, $challenge): ?string {
                    $parameters = $challenge->authParameters;
                    if (!($realm = $parameters["realm"]) || !($uri = $parameters["uri"]) || !($algorithm = $parameters["algorithm"]) || !($nonce = $parameters["nonce"]) || !($qop = $parameters["qop"]) || !($opaque = $parameters["opaque"])) {
                        return null;
                    }
                    $algo = match ($parameters["algorithm"]) {
                        "SHA-512-256" => "sha512",
                        "SHA-256" => "sha256",
                        default => "md5"
                    };
                    $nc = sprintf("%08x", $this->previousFailureCount);
                    $cnonce = hash($algo, ProcessInfo::processInfo()->globallyUniqueString);
                    $HA1 = hash($algo, "$username:$realm:$password");
                    $HA2 = hash($algo, "$request->httpMethod:$uri");
                    $response = hash($algo, "$HA1:$nonce:$nc:$cnonce:$qop:$HA2");
                    return "username=\"$username\", realm=\"$realm\", uri=\"$uri\", algorithm=\"$algorithm\", nonce=\"$nonce\", nc=\"$nc\", cnonce=\"$cnonce\", qop=\"$qop\", response=\"$response\", opaque=\"$opaque\"";
                })(),
                default => null
            })) {
                fatal_error("This URLSession implementation doesn't currently handle $method authentication.");
            }
            $task->authRequest = $request;
            $task->authRequest->setValueForHttpHeaderField("$challenge->authScheme $authorization", "Authorization");
        };
    }

    /**
     * Resumes the task, if it is suspended.
     */
    public function resume(): void
    {
        if ($this->state === URLSessionTaskState::canceling || $this->state === URLSessionTaskState::completed) {
            return;
        }
        if ($this->suspendCount > 0) {
            $this->suspendCount -= 1;
        }
        $this->updateTaskState();
        if ($this->suspendCount === 0) {
            $this->hasTriggeredResume = true;
            $this->getProtocol(function (?URLProtocol $protocol): void {
                if ($protocol) {
                    $protocol->startLoading();
                } elseif ($this->error === null) {
                    $this->error = new Error(URLErrorDomain, URLErrorUnsupportedURL, new Dictionary([LocalizedDescriptionKey => "Unsupported URL", URLErrorFailingURLErrorKey => $this->originalRequest?->url]));
                    /** @noinspection PhpUnhandledExceptionInspection */
                    new ProtocolClient()->urlProtocolTaskDidFailWithError($this, $this->error);
                }
            });
        }
    }

    /**
     * Temporarily suspends a task.
     */
    public function suspend(): void
    {
        if ($this->state === URLSessionTaskState::canceling || $this->state === URLSessionTaskState::completed) {
            return;
        }
        $this->suspendCount += 1;
        $this->updateTaskState();
        if ($this->suspendCount === 1) {
            $this->getProtocol(function (?URLProtocol $protocol): void {
                $protocol?->stopLoading();
            });
        }
    }

    /**
     * Cancels the task.
     */
    public function cancel(): void
    {
        if ($this->state === URLSessionTaskState::canceling || $this->state === URLSessionTaskState::completed) {
            return;
        }
        $this->updateTaskState();
        $this->getProtocol(function (?URLProtocol $protocol): void {
            if (!$protocol instanceof URLProtocol) {
                return;
            }
            $this->error = new Error(CocoaErrorDomain, UserCancelledError, new Dictionary([URLErrorFailingURLErrorKey => $this->originalRequest?->url]));
            $protocol->stopLoading();
            /** @noinspection PhpUnhandledExceptionInspection */
            $protocol->client?->urlProtocolDidFailWithError($protocol, $this->error);
        });
    }
}
