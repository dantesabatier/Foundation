<?php

declare(strict_types=1);

namespace Sabatier\Foundation\Networking;

use Exception;
use Override;
use Sabatier\Foundation\Dictionary;
use Sabatier\Foundation\Error;
use Sabatier\Foundation\FileHandle;
use Sabatier\Foundation\FileManager;
use Sabatier\Foundation\Number;
use Sabatier\Foundation\ProcessInfo;
use Sabatier\Foundation\SearchPathDirectory;
use Sabatier\Foundation\SearchPathDomainMask;
use Sabatier\Foundation\SystemRandomNumberGenerator;
use Sabatier\Foundation\URL;
use Throwable;
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
    public InternalState $internalState {
        get => $this->internalState ??= InternalState::initial();
    }
    private(set) EasyHandle $easyHandle {
        get => $this->easyHandle ??= new EasyHandle($this);
    }
    private URL $tempFileURL {
        /** @noinspection PhpUnhandledExceptionInspection */
        get {
            if (isset($this->tempFileURL)) {
                return $this->tempFileURL;
            }
            $this->tempFileURL = FileManager::default()->url(SearchPathDirectory::cachesDirectory, SearchPathDomainMask::local, null, true)->appendingPathComponent(uniqid((string)new SystemRandomNumberGenerator()->next(), true))->appendPathExtension($this->task->originalRequest?->url?->pathExtension ?? "");
            FileManager::default()->createFile($this->tempFileURL->path, null);
            return $this->tempFileURL;
        }
    }

    public static function enableLibcurlDebugOutput(): bool
    {
        return new Number(ProcessInfo::processInfo()->environment[URLSessionDebugLibcurl] ?? false)->boolValue;
    }

    #[Override]
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

    #[Override]
    public function startLoading(): void
    {
        $this->resume();
    }

    #[Override]
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

    private function createTransferBodyDataDrain(): DataDrain
    {
        /** @noinspection PhpUnhandledExceptionInspection */
        return match ($this->task->session->behaviour($this->task)->rawValue) {
            TaskBehaviourRawValue::noDelegate => DataDrain::ignore(),
            TaskBehaviourRawValue::taskDelegate, TaskBehaviourRawValue::dataCompletionHandler => DataDrain::inMemory(),
            TaskBehaviourRawValue::downloadCompletionHandler => DataDrain::toFile($this->tempFileURL, FileHandle::fileHandleForWritingToURL($this->tempFileURL))
        };
    }

    private function createTransferState(URL $url, TaskBody $body): TransferState
    {
        return match ($body->rawValue) {
            TaskBodyRawValue::none, TaskBodyRawValue::data, TaskBodyRawValue::file, TaskBodyRawValue::stream => new TransferState($url, bodyDataDrain: $this->createTransferBodyDataDrain())
        };
    }

    public function startNewTransfer(URLRequest $request): void
    {
        $task = $this->task;
        $task->currentRequest = $request;
        $task->response = null;
        self::setProperty("", "responseData", $this->request);
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

    public function resume(): void
    {
        if ($this->internalState->rawValue === InternalStateRawValue::initial) {
            $request = $this->task->originalRequest ?? fatal_error("Task has no original request.");
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


    #[Override]
    public function didReceiveData(string $data): EasyHandleAction
    {
        $this->internalState->rawValue === InternalStateRawValue::transferInProgress ?: fatal_error("Received body data, but no transfer in progress.");
        /** @var TransferState $ts */
        $ts = $this->internalState->transferState;
        if ($response = $this->validateHeaderComplete($ts)) {
            $ts->response = $response;
        }
        if (($httpResponse = $ts->response) && $httpResponse instanceof HTTPURLResponse && in_range($httpResponse->statusCode, HTTPStatusCode::movedPermanently, HTTPStatusCode::permanentRedirect)) {
            if ($this instanceof HTTPURLProtocol) {
                /** @psalm-suppress PossiblyNullOperand */
                $this->lastRedirectBody .= $data;
            }
            return EasyHandleAction::proceed;
        }
        $this->internalState = InternalState::transferInProgress($ts->byAppendingBodyData($data));
        return EasyHandleAction::proceed;
    }

    #[Override]
    public function fill(mixed $buffer, int $length): EasyHandleWriteBufferResult
    {
        $this->internalState->rawValue === InternalStateRawValue::transferInProgress ?: fatal_error("Requested to fill buffer, but transfer isn't in progress.");
        return EasyHandleWriteBufferResult::bytes(is_resource($buffer) ? fread($buffer, $length) : "");
    }

    #[Override]
    public function transferCompleted(?Error $error): void
    {
        if ($error instanceof Error) {
            if ($this->internalState->rawValue !== InternalStateRawValue::transferFailed) {
                $this->internalState = InternalState::transferFailed();
                $this->completeTaskWithError($error);
                $this->failWithError($error, $this->request);
            }
            return;
        }
        if ($this->internalState->rawValue === InternalStateRawValue::transferFailed) {
            return;
        }
        $this->internalState->rawValue === InternalStateRawValue::transferInProgress ?: fatal_error("Transfer completed, but it wasn't in progress.");
        $request = $this->task->currentRequest ?? fatal_error("Transfer completed, but there's no current request.");
        /** @var TransferState $ts */
        $ts = $this->internalState->transferState;
        if ($response = $this->task->response) {
            $ts->response = $response;
        }
        $response = $ts->response ?? fatal_error("Transfer completed, but there's no response.");
        $this->internalState = InternalState::transferCompleted($response, $ts->bodyDataDrain);
        $action = $this->completionAction($request, $response);
        switch ($action->rawValue) {
            case CompletionActionRawValue::completeTask:
                $this->completeTask();
                break;
            case CompletionActionRawValue::failWithError:
                $this->internalState = InternalState::transferFailed();
                $error = new Error(URLErrorDomain, $action->errorCode, new Dictionary([LocalizedDescriptionKey => localized_string("Completion failure")]));
                $this->failWithError($error, $request);
                break;
            case CompletionActionRawValue::redirectWithRequest:
                /** @var URLRequest $newRequest */
                $newRequest = $action->newRequest;
                $this->redirectFor($newRequest);
                break;
        }
    }

    #[Override]
    public function updateProgressMeter(EasyHandleProgress $progress): void
    {
        $progressReporter = $this->task->progress;
        $progressReporter->totalUnitCount = $progress->totalBytesExpectedToReceive + $progress->totalBytesExpectedToSend;
        $progressReporter->completedUnitCount = $progress->totalBytesReceived + $progress->totalBytesSent;
    }

    public function validateHeaderComplete(TransferState $transferState): ?URLResponse
    {
        $transferState->isHeaderComplete() ?: fatal_error("Received body data, but the header is not complete, yet.");
        return null;
    }

    /**
     * @throws Throwable
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

    public function completeTaskWithError(Error $error): void
    {
        $task = $this->task;
        $task->error = $error;
        $this->internalState->rawValue === InternalStateRawValue::transferFailed ?: fatal_error("Trying to complete the task, but its transfer isn't complete / failed.");
        $this->internalState = InternalState::taskCompleted();
        $task->session->remove($this->easyHandle);
    }

    public function failWithError(Error $error, URLRequest $request): void
    {
        if ($error->domain !== URLErrorDomain) {
            $error = new Error(URLErrorDomain, $error->code, new Dictionary([UnderlyingErrorKey => $error, URLErrorFailingURLErrorKey => $request->url, LocalizedDescriptionKey => $error->localizedDescription]));
        }
        $this->client?->urlProtocolDidFailWithError($this, $error);
    }

    public function askDelegateHowToProceedAfterCompleteResponse(URLResponse $response, URLSessionDataDelegate $delegate): void
    {
        if ($this->internalState->rawValue !== InternalStateRawValue::transferInProgress) {
            return;
        }
        $task = $this->task;
        $task instanceof URLSessionDataTask ?: fatal_error("Asking delegate how to proceed after complete response, but the task is not a data task.");
        /** @var TransferState $ts */
        $ts = $this->internalState->transferState;
        $this->internalState = InternalState::waitingForResponseCompletionHandler($ts);
        /** @psalm-suppress ArgumentTypeCoercion */
        $delegate->urlSessionDataTaskDidReceiveResponse($task->session, $task, $response, function (URLSessionResponseDisposition $disposition): void {
            $this->didCompleteResponseCallback($disposition);
        });
    }

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

    public function completeTask(): void
    {
        $this->internalState->rawValue === InternalStateRawValue::transferCompleted ?: fatal_error("Trying to complete the task, but its transfer is already complete.");
        $task = $this->task;
        $task->response = $this->internalState->response;
        /** @var DataDrain $bodyDataDrain */
        $bodyDataDrain = $this->internalState->bodyDataDrain;
        $data = $bodyDataDrain->bodyData;
        switch ($bodyDataDrain->rawValue) {
            case DataDrainRawValue::inMemory:
                $this->client?->urlProtocolDidLoad($this, $data);
                break;
            case DataDrainRawValue::toFile:
                // Flush and release the drain before anyone reads the delivered file.
                /** @noinspection PhpUnhandledExceptionInspection */
                $bodyDataDrain->fileHandle?->close();
                break;
            case DataDrainRawValue::ignore:
                break;
        }
        if ($task instanceof URLSessionDownloadTask) {
            if (!($fileURL = $bodyDataDrain->fileURL)) {
                $fileURL = $this->tempFileURL;
                try {
                    FileManager::default()->createFile($fileURL->path, $data);
                } catch (Exception) {
                }
            }
            self::setProperty($fileURL, "temporaryFileURL", $this->request);
        }
        $this->internalState = InternalState::initial();
        $task->session->remove($this->easyHandle);
        $this->client?->urlProtocolDidFinishLoading($this);
        if ($task->state === URLSessionTaskState::completed) {
            $this->internalState = InternalState::taskCompleted();
        }
    }

    public function completionAction(URLRequest $request, URLResponse $response): CompletionAction
    {
        return CompletionAction::completeTask();
    }
}
