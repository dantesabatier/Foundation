<?php

namespace Sabatier\Foundation\Networking;

use Exception;
use Sabatier\Foundation\Dictionary;
use Sabatier\Foundation\Error;
use Sabatier\Foundation\URL;
use Sabatier\Foundation\URLComponents;
use function Sabatier\Foundation\fatal_error;
use function Sabatier\Foundation\in_range;
use function Sabatier\Foundation\substring_from_index;
use const Sabatier\Foundation\LocalizedDescriptionKey;
use const Sabatier\Foundation\URLErrorBadServerResponse;
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

    public function configureEasyHandle(URLRequest $request, TaskBody $body): void
    {
        if ($request->httpMethod !== HTTPRequestMethod::get) {
            trigger_error("WebSocket tasks must use GET");
            $this->internalState = InternalState::transferFailed();
            $error = new Error(URLErrorDomain, URLErrorUnsupportedURL, new Dictionary([LocalizedDescriptionKey => "WebSocket task must use GET httpMethod", URLErrorFailingURLErrorKey => $request->url]));
            $this->transferCompleted($error);
            return;
        }
        $absoluteURL = $request->url->absoluteURL;
        $components = new URLComponents($absoluteURL->absoluteString);
        $components->scheme = $absoluteURL->scheme === "wss" ? "ssl" : "tcp";
        $components->port = $absoluteURL->port ?? $absoluteURL->scheme === "wss" ? 443 : 80;
        /** @var URL $url */
        $url = $components->url;
        $easyHandle = $this->easyHandle;
        $easyHandle->setURL($url);
        $easyHandle->setTimeout((int)$request->timeoutInterval);
    }

    public function sendWebSocketData(string $data, URLSessionWebSocketOperation $operation): void
    {
        $this->easyHandle->sendWebSocketsData($data, $operation);
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
        /** @var URLSessionWebSocketTask $task */
        $task = $this->task;
        $task->protocolPicked = $response->valueForHttpHeaderField("Sec-WebSocket-Protocol");
        $task->handshakeCompleted = true;
        $this->client?->urlProtocolDidReceiveCacheStoragePolicy($this, $response, URLCacheStoragePolicy::notAllowed);
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
        $this->notifyTaskAboutReceivedData($data, $this->easyHandle->getWebSocketFlags());
        $this->internalState = InternalState::transferInProgress($ts->byAppendingBodyData($data));
        return EasyHandleAction::proceed;
    }

    /**
     * @throws Exception
     */
    private function notifyTaskAboutReceivedData(string $data, URLSessionWebSocketOperation $operation): void
    {
        $task = $this->task;
        if ($task->session->behaviour($task)->rawValue !== TaskBehaviourRawValue::taskDelegate || !$task instanceof URLSessionWebSocketTask) {
            fatal_error("WebSocket internal invariant violated");
        }
        switch ($operation) {
            case URLSessionWebSocketOperation::close:
                $reasonData = "";
                $closeCode = URLSessionWebSocketTaskCloseCode::normalClosure;
                if (strlen($data) >= 2) {
                    $reasonData = substring_from_index($data, 2);
                    [$byte1,] = array_values(unpack('C*', $data));
                    $closeCode = URLSessionWebSocketTaskCloseCode::tryFrom($byte1 & 0b00001111) ?? URLSessionWebSocketTaskCloseCode::unsupportedData;
                }
                $task->close($closeCode, $reasonData);
                break;
            case URLSessionWebSocketOperation::pong:
                $task->noteReceivedPong();
                break;
            case URLSessionWebSocketOperation::binary:
                $task->appendReceivedMessage(URLSessionWebSocketTaskMessage::data($data));
                break;
            case URLSessionWebSocketOperation::text:
                $task->appendReceivedMessage(URLSessionWebSocketTaskMessage::string($data));
                break;
            case URLSessionWebSocketOperation::cont:
            case URLSessionWebSocketOperation::ping:
                trigger_error("Unexpected message received from server $data");
                $this->internalState = InternalState::transferFailed();
                $error = new Error(URLErrorDomain, URLErrorBadServerResponse, new Dictionary([LocalizedDescriptionKey => "Unexpected message received from server", URLErrorFailingURLErrorKey => $this->request->url]));
                $this->transferCompleted($error);
                break;
        }
    }
}
