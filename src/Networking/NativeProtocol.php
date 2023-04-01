<?php

namespace Sabatier\Foundation\Networking;

use Exception;
use Sabatier\Foundation\Dictionary;
use Sabatier\Foundation\Error;
use Sabatier\Foundation\FileHandle;
use Sabatier\Foundation\FileManager;
use Sabatier\Foundation\ProcessInfo;
use Sabatier\Foundation\SearchPathDirectory;
use Sabatier\Foundation\SearchPathDomainMask;
use Sabatier\Foundation\SystemRandomNumberGenerator;
use Sabatier\Foundation\URL;
use function Sabatier\Foundation\fatal_error;
use function Sabatier\Foundation\in_range;
use function Sabatier\Foundation\localized_string;
use function Sabatier\Foundation\request_concrete_implementation;
use const Sabatier\Foundation\CocoaErrorDomain;
use const Sabatier\Foundation\LocalizedDescriptionKey;
use const Sabatier\Foundation\UnderlyingErrorKey;
use const Sabatier\Foundation\URLErrorDomain;
use const Sabatier\Foundation\URLErrorFailingURLErrorKey;
use const Sabatier\Foundation\UserCancelledError;

/** @internal */
abstract class NativeProtocol extends URLProtocol implements EasyHandleDelegate
{
    public ?string $lastRedirectBody = null;
    public InternalState $internalState;
    public readonly EasyHandle $easyHandle;
    public readonly URL $tempFileURL;

    public function __construct(URLSessionTask $task, ?CachedURLResponse $cachedResponse = null, ?URLProtocolClient $client = null)
    {
        parent::__construct($task, $cachedResponse, $client);
        unset($this->internalState);
        unset($this->easyHandle);
        unset($this->tempFileURL);
    }

    /**
     * @throws Exception
     */
    public function __get(string $name)
    {
        return $this->$name = match ($name) {
            "internalState" => InternalState::initial(),
            "easyHandle" => new EasyHandle($this),
            "tempFileURL" => (function (): URL {
                $tempFileURL = FileManager::default()->url(SearchPathDirectory::cachesDirectory, SearchPathDomainMask::local, null, true)->appendingPathComponent(uniqid((string)(new SystemRandomNumberGenerator())->next(), true))->appendPathExtension($this->task->originalRequest?->url?->pathExtension ?? "");
                FileManager::default()->createFile($tempFileURL->path, null);
                return $tempFileURL;
            })(),
            default => $this->valueForUndefinedKey($name)
        };
    }

    public static function enableLibcurlDebugOutput(): bool
    {
        return ProcessInfo::processInfo()->environment["URLSessionDebugLibcurl"] !== null;
    }

    public static function canonicalRequest(URLRequest $request): URLRequest
    {
        return $request;
    }

    public function canCache(CachedURLResponse $cacheable): bool
    {
        return false;
    }

    public function canRespondFromCache(CachedURLResponse $cachedResponse): bool
    {
        $task = $this->task;
        if ($task instanceof URLSessionDataTask && ($cache = $task->session->configuration->urlCache)) {
            $cache->removeCachedResponseForDataTask($task);
        }
        return false;
    }

    /**
     * @throws Exception
     */
    public function startLoading(): void
    {
        $this->resume();
    }

    /**
     * @throws Exception
     */
    public function stopLoading(): void
    {
        if ($this->task->state === URLSessionTaskState::suspended) {
            $this->suspend();
        } else {
            $this->internalState = InternalState::transferFailed();
            $error = $this->task->error ?? fatal_error();
            $this->completeTaskWithError($error);
        }
    }

    /**
     * @throws Exception
     */
    private function createTransferBodyDataDrain(): DataDrain
    {
        $task = $this->task;
        $session = $task->session;
        $behaviour = $session->behaviour($task);
        return match ($behaviour->rawValue) {
            TaskBehaviourRawValue::noDelegate, TaskBehaviourRawValue::taskDelegate, TaskBehaviourRawValue::dataCompletionHandler => DataDrain::inMemory(),
            TaskBehaviourRawValue::downloadCompletionHandler => DataDrain::toFile($this->tempFileURL, FileHandle::fileHandleForUpdatingURL($this->tempFileURL))
        };
    }

    /**
     * @throws Exception
     */
    private function createTransferState(URL $url, TaskBody $body): TransferState
    {
        $dataDrain = $this->createTransferBodyDataDrain();
        return match ($body->rawValue) {
            TaskBodyRawValue::none, TaskBodyRawValue::data, TaskBodyRawValue::file, TaskBodyRawValue::stream => new TransferState($url, bodyDataDrain: $dataDrain)
        };
    }

    /**
     * @throws Exception
     */
    public function startNewTransfer(URLRequest $request): void
    {
        $task = $this->task;
        $task->currentRequest = $request;
        $url = $request->url;
        $task->getBody(function (TaskBody $body) use ($task, $request, $url): void {
            $this->internalState = InternalState::transferReady($this->createTransferState($url, $body));
            $request = $task->authRequest ?? $request;
            $this->configureEasyHandle($request, $body);
            if ($task->suspendCount < 1) {
                $this->resume();
            }
        });
    }

    /**
     * @throws Exception
     */
    public function resume(): void
    {
        if ($this->internalState->rawValue === InternalStateRawValue::initial) {
            if (!($request = $this->task->originalRequest)) {
                fatal_error("Task has no original request.");
            }
            if (($cachedResponse = $this->cachedResponse) && $this->canRespondFromCache($cachedResponse)) {
                $this->internalState = InternalState::fulfillingFromCache($cachedResponse);
                $this->client?->urlProtocolCachedResponseIsValid($this, $cachedResponse);
                $this->client?->urlProtocolDidReceiveCacheStoragePolicy($this, $cachedResponse->response, URLCacheStoragePolicy::notAllowed);
                $this->client?->urlProtocolDidLoad($this, $cachedResponse->data);
                $this->client?->urlProtocolDidFinishLoading($this);
                $this->internalState = InternalState::taskCompleted();
            } else {
                $this->startNewTransfer($request);
            }
        }
        if ($this->internalState->rawValue === InternalStateRawValue::transferReady) {
            /** @var TransferState $ts */
            $ts = $this->internalState->transferState;
            $this->internalState = InternalState::transferInProgress($ts);
            $this->task->session->add($this->easyHandle);
        }
    }

    public function suspend(): void
    {
        /** @var TransferState $ts */
        $ts = $this->internalState->transferState;
        if ($this->internalState->rawValue === InternalStateRawValue::transferInProgress) {
            $this->internalState = InternalState::transferReady($ts);
        }
    }

    abstract public function configureEasyHandle(URLRequest $request, TaskBody $body): void;

    /**
     * @throws Exception
     */
    public function didReceiveData(string $data): EasyHandleAction
    {
        if ($this->internalState->rawValue !== InternalStateRawValue::transferInProgress) {
            fatal_error("Received body data, but no transfer in progress.");
        }
        /** @var TransferState $ts */
        $ts = $this->internalState->transferState;
        if ($response = $this->validateHeaderComplete($ts)) {
            $ts->response = $response;
        }
        if (($httpResponse = $ts->response) && $httpResponse instanceof HTTPURLResponse && in_range($httpResponse->statusCode, 301, 308)) {
            if ($this instanceof HTTPURLProtocol) {
                /** @psalm-suppress PossiblyNullOperand */
                $this->lastRedirectBody .= $data;
            }
            return EasyHandleAction::proceed;
        }
        $this->notifyDelegateAboutReceivedData($data);
        $this->internalState = InternalState::transferInProgress($ts->byAppendingBodyData($data));
        return EasyHandleAction::proceed;
    }

    /**
     * @throws Exception
     */
    public function fill(mixed $buffer): EasyHandleWriteBufferResult
    {
        if ($this->internalState->rawValue !== InternalStateRawValue::transferInProgress) {
            fatal_error("Requested to fill write buffer, but transfer isn't in progress.");
        }
        return EasyHandleWriteBufferResult::bytes(fgets($buffer));
    }

    /**
     * @throws Exception
     */
    public function transferCompleted(?Error $error): void
    {
        if ($error instanceof Error) {
            $this->internalState = InternalState::transferFailed();
            $this->failWithError($error, $this->request);
            return;
        }
        if ($this->internalState->rawValue !== InternalStateRawValue::transferInProgress) {
            fatal_error("Transfer completed, but it wasn't in progress.");
        }
        if (!($request = $this->task->currentRequest)) {
            fatal_error("Transfer completed, but there's no current request.");
        }
        /** @var TransferState $ts */
        $ts = $this->internalState->transferState;
        if ($response = $this->task->response) {
            $ts->response = $response;
        }
        if (!($response = $ts->response)) {
            fatal_error("Transfer completed, but there's no response.");
        }
        $this->internalState = InternalState::transferCompleted($response, $ts->bodyDataDrain);
        $action = $this->completionAction($request, $response);
        switch ($action->rawValue) {
            case CompletionActionRawValue::completeTask:
                $this->completeTask();
                break;
            case CompletionActionRawValue::failWithError:
                $this->internalState = InternalState::transferFailed();
                $error = new Error(URLErrorDomain, $action->errorCode, new Dictionary([LocalizedDescriptionKey => "Completion failure"]));
                $this->failWithError($error, $request);
                break;
            case CompletionActionRawValue::redirectWithRequest:
                /** @var URLRequest $newRequest */
                $newRequest = $action->newRequest;
                $this->redirectFor($newRequest);
                break;
        }
    }

    public function updateProgressMeter(EasyHandleProgress $progress): void
    {
        $progressReporter = $this->task->progress;
        $progressReporter->totalUnitCount = $progress->totalBytesExpectedToReceive + $progress->totalBytesExpectedToSend;
        $progressReporter->completedUnitCount = $progress->totalBytesReceived + $progress->totalBytesSent;
    }

    /**
     * @throws Exception
     */
    public function validateHeaderComplete(TransferState $transferState): ?URLResponse
    {
        if (!$transferState->isHeaderComplete()) {
            fatal_error("Received body data, but the header is not complete, yet.");
        }
        return null;
    }

    /**
     * @throws Exception
     */
    public function notifyDelegateAboutReceivedData(string $data): void
    {
        $task = $this->task;
        $session = $task->session;
        $behaviour = $task->session->behaviour($task);
        switch ($behaviour->rawValue) {
            case TaskBehaviourRawValue::taskDelegate:
                /** @var URLSessionDelegate $delegate */
                $delegate = $behaviour->taskDelegate;
                if ($delegate instanceof URLSessionDataDelegate && $task instanceof URLSessionDataTask) {
                    $delegate->urlSessionDataTaskReceiveData($session, $task, $data);
                } elseif ($task instanceof URLSessionDownloadTask && $delegate instanceof URLSessionDownloadDelegate) {
                    $bytesWritten = strlen($data);
                    $task->countOfBytesReceived += $bytesWritten;
                    $delegate->urlSessionDownloadTaskDidWriteData($session, $task, $bytesWritten, $task->countOfBytesReceived, $task->countOfBytesExpectedToReceive);
                }
                break;
            case TaskBehaviourRawValue::noDelegate:
            case TaskBehaviourRawValue::dataCompletionHandler:
            case TaskBehaviourRawValue::downloadCompletionHandler:
                break;
        }
    }

    /**
     * @throws Exception
     */
    public function notifyDelegateAboutUploadedData(int $count): void
    {
        $task = $this->task;
        $session = $task->session;
        $behaviour = $session->behaviour($task);
        switch ($behaviour->rawValue) {
            case TaskBehaviourRawValue::taskDelegate:
                $delegate = $behaviour->taskDelegate;
                if ($delegate instanceof URLSessionTaskDelegate) {
                    $task->countOfBytesSent += $count;
                    $session->delegateQueue->addOperationWithBlock(function () use ($session, $task, $count, $delegate): void {
                        $delegate->urlSessionTaskDidSendBodyData($session, $task, $count, $task->countOfBytesSent, $task->countOfBytesExpectedToSend);
                    });
                }
                break;
            default:
                break;
        }
    }

    /**
     * @throws Exception
     */
    public function completeTaskWithError(Error $error): void
    {
        $task = $this->task;
        $task->error = $error;
        if ($this->internalState->rawValue !== InternalStateRawValue::transferFailed) {
            fatal_error("Trying to complete the task, but its transfer isn't complete / failed.");
        }
        $this->internalState = InternalState::taskCompleted();
        $task->session->remove($this->easyHandle);
    }

    /**
     * @throws Exception
     */
    public function failWithError(Error $error, URLRequest $request): void
    {
        if ($error->domain !== URLErrorDomain) {
            $error = new Error(URLErrorDomain, $error->code, new Dictionary([UnderlyingErrorKey => $error, URLErrorFailingURLErrorKey => $request->url, LocalizedDescriptionKey => localized_string($error->localizedDescription)]));
        }
        $this->client?->urlProtocolDidFailWithError($this, $error);
    }

    /**
     * @throws Exception
     */
    public function askDelegateHowToProceedAfterCompleteResponse(URLResponse $response, URLSessionDataDelegate $delegate): void
    {
        if ($this->internalState->rawValue !== InternalStateRawValue::transferInProgress) {
            return;
        }
        $task = $this->task;
        if (!$task instanceof URLSessionDataTask) {
            fatal_error();
        }
        /** @var TransferState $ts */
        $ts = $this->internalState->transferState;
        $this->internalState = InternalState::waitingForResponseCompletionHandler($ts);
        $session = $task->session;
        $delegate->urlSessionDataTaskDidReceiveResponse($session, $task, $response, function (URLSessionResponseDisposition $disposition): void {
            $this->didCompleteResponseCallback($disposition);
        });
    }

    /**
     * @throws Exception
     */
    public function didCompleteResponseCallback(URLSessionResponseDisposition $disposition): void
    {
        if ($this->internalState->rawValue !== InternalStateRawValue::waitingForResponseCompletionHandler) {
            return;
        }
        /** @var TransferState $ts */
        $ts = $this->internalState->transferState;
        switch ($disposition) {
            case URLSessionResponseDisposition::cancel:
                $error = new Error(CocoaErrorDomain, UserCancelledError);
                $this->completeTaskWithError($error);
                $this->client?->urlProtocolDidFailWithError($this, $error);
                break;
            case URLSessionResponseDisposition::allow:
                $this->internalState = InternalState::transferInProgress($ts);
                break;
            case URLSessionResponseDisposition::becomeDownload:
            case URLSessionResponseDisposition::becomeStream:
                break;
        }
    }

    public function redirectFor(URLRequest $request): void
    {
        request_concrete_implementation($this, __FUNCTION__);
    }

    /**
     * @throws Exception
     */
    public function completeTask(): void
    {
        if ($this->internalState->rawValue !== InternalStateRawValue::transferCompleted) {
            fatal_error("Received body data, but no transfer in progress.");
        }
        $task = $this->task;
        $task->response = $this->internalState->response;
        /** @var DataDrain $bodyDataDrain */
        $bodyDataDrain = $this->internalState->bodyDataDrain;
        $data = $bodyDataDrain->bodyData;
        if ($bodyDataDrain->rawValue == DataDrainRawValue::inMemory) {
            $this->client?->urlProtocolDidLoad($this, $data);
        } elseif ($bodyDataDrain->rawValue == DataDrainRawValue::toFile || $task instanceof URLSessionDownloadTask) {
            self::setProperty($bodyDataDrain->fileURL, "temporaryFileURL", $this->request);
        }
        $this->client?->urlProtocolDidFinishLoading($this);
        $this->internalState = InternalState::taskCompleted();
        $task->session->remove($this->easyHandle);
    }

    public function completionAction(URLRequest $request, URLResponse $response): CompletionAction
    {
        return CompletionAction::completeTask();
    }
}
