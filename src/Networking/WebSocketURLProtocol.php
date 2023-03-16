<?php

namespace Sabatier\Foundation\Networking;

use Sabatier\Foundation\Dictionary;
use Sabatier\Foundation\Error;
use function Sabatier\Foundation\fatal_error;
use function Sabatier\Foundation\in_range;
use const Sabatier\Foundation\LocalizedDescriptionKey;
use const Sabatier\Foundation\URLErrorDomain;
use const Sabatier\Foundation\URLErrorFailingURLErrorKey;
use const Sabatier\Foundation\URLErrorUnsupportedURL;

/** @internal */
class WebSocketURLProtocol extends HTTPURLProtocol
{

    public static function canInit(URLRequest $request): bool
    {
        return match ($request->url->scheme) {
            "ws", "wss" => true,
            default => false
        };
    }

    public function canCache(CachedURLResponse $cacheable): bool
    {
        return false;
    }

    public function canRespondFromCache(CachedURLResponse $cachedResponse): bool
    {
        return false;
    }

    public function didReceiveResponse(): void
    {
        if ($this->internalState->rawValue !== InternalStateRawValue::transferInProgress) {
            fatal_error("Transfer not in progress.");
        }
        $response = $this->internalState->transferState?->response;
        if (!$response instanceof HTTPURLResponse) {
            fatal_error("Header complete, but not URL response.");
        }
        $task = $this->task;
        if (!$task instanceof URLSessionWebSocketTask) {
            return;
        }
        $task->protocolPicked = $response->valueForHttpHeaderField("Sec-WebSocket-Protocol");
        $task->handshakeCompleted = true;
        $this->client?->urlProtocolDidReceiveCacheStoragePolicy($this, $response, URLCacheStoragePolicy::notAllowed);
    }

    public function configureEasyHandle(URLRequest $request, TaskBody $body): void
    {
        if ($request->httpMethod === HTTPRequestMethod::get) {
            trigger_error("WebSocket tasks must use GET");
            $this->internalState = InternalState::transferFailed();
            $error = new Error(URLErrorDomain, URLErrorUnsupportedURL, new Dictionary([LocalizedDescriptionKey => "WebSocket task must use GET httpMethod", URLErrorFailingURLErrorKey => $request->url]));
            $this->transferCompleted($error);
            return;
        }
        parent::configureEasyHandle($request, $body);
        $easyHandle = $this->easyHandle;
        $easyHandle->setAllowedProtocolsToAll();
        $task = $this->task;
        if (!$task instanceof URLSessionWebSocketTask) {
            return;
        }
        $easyHandle->setPreferredReceiveBufferSize($task->maximumMessageSize);
    }

    public function completionAction(URLRequest $request, URLResponse $response): CompletionAction
    {
        $httpURLResponse = $response;
        if (!$httpURLResponse instanceof HTTPURLResponse) {
            fatal_error("Response was not HTTPURLResponse");
        }
        if ($request = $this->redirectRequest($request, $httpURLResponse)) {
            return CompletionAction::redirectWithRequest($request);
        }
        return CompletionAction::completeTask();
    }

    public function completeTask(): void
    {
        $task = $this->task;
        if ($task instanceof URLSessionWebSocketTask) {
            $task->close(URLSessionWebSocketTaskCloseCode::normalClosure);
        }
        parent::completeTask();
    }

    public function didReceiveData(string $data): EasyHandleAction
    {
        if ($this->internalState->rawValue !== InternalStateRawValue::transferInProgress) {
            fatal_error("Received web socket data, but no transfer in progress.");
        }
        /** @var TransferState $ts */
        $ts = $this->internalState->transferState;
        if ($response = $this->validateHeaderComplete($ts)) {
            $ts->response = $response;
        }
        if (($httpResponse = $ts->response) && $httpResponse instanceof HTTPURLResponse && in_range($httpResponse->statusCode, 301, 308)) {
            /** @psalm-suppress PossiblyNullOperand */
            $this->lastRedirectBody .= $data;
        }
        $this->notifyTaskAboutReceivedData($data);
        $this->internalState = InternalState::transferInProgress($ts->byAppendingBodyData($data));
        return EasyHandleAction::proceed;
    }

    private function notifyTaskAboutReceivedData(string $data): void
    {
    }
}
