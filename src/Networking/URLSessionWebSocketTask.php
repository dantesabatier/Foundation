<?php

namespace Sabatier\Foundation\Networking;

use Closure;
use Exception;
use Sabatier\Foundation\ArrayClass;
use Sabatier\Foundation\Dictionary;
use Sabatier\Foundation\Error;
use const Sabatier\Foundation\LocalizedDescriptionKey;
use const Sabatier\Foundation\URLErrorBadServerResponse;
use const Sabatier\Foundation\URLErrorDomain;
use const Sabatier\Foundation\URLErrorFailingURLErrorKey;
use const Sabatier\Foundation\URLErrorNetworkConnectionLost;
use const Sabatier\Foundation\URLErrorUnsupportedURL;

/**
 * A URL session task that communicates over the WebSockets protocol standard.
 */
class URLSessionWebSocketTask extends URLSessionTask
{
    /** @var int The maximum number of bytes to buffer before the receive call fails with an error. This value includes the sum of all bytes from continuation frames. Receive calls will fail once the task reaches this limit. */
    public int $maximumMessageSize = 1024 * 1024;
    /** @var URLSessionWebSocketTaskCloseCode A code that indicates the reason a connection closed. */
    public URLSessionWebSocketTaskCloseCode $closeCode = URLSessionWebSocketTaskCloseCode::invalid;
    /** @var string|null A block of data that provides further information about why a connection closed. */
    public ?string $closeReason = null;
    /** @internal */
    public ?string $protocolPicked = null;
    /** @internal */
    public bool $handshakeCompleted = false;
    private ?Error $taskError = null;
    /** @var ArrayClass<array{URLSessionWebSocketTaskMessage, Closure(Error|null): void}> */
    private readonly ArrayClass $sendBuffer;
    /** @var ArrayClass<URLSessionWebSocketTaskMessage> */
    private readonly ArrayClass $receiveBuffer;
    /** @var ArrayClass<Closure(URLSessionWebSocketTaskMessage|null, Error|null): void> */
    private readonly ArrayClass $receiveCompletionHandlers;
    /** @var ArrayClass<Closure(Error|null): void> */
    private readonly ArrayClass $pongCompletionHandlers;
    /** @var array{URLSessionWebSocketTaskCloseCode, string}|null */
    private ?array $closeMessage = null;

    public function __construct(URLSession $session, URLRequest $request, int $taskIdentifier, ?TaskBody $body = null)
    {
        parent::__construct($session, $request, $taskIdentifier, $body);
        $this->sendBuffer = new ArrayClass();
        $this->receiveBuffer = new ArrayClass();
        $this->receiveCompletionHandlers = new ArrayClass();
        $this->pongCompletionHandlers = new ArrayClass();
    }

    /**
     * Sends a WebSocket message, receiving the result in a completion handler.
     *
     * If an error occurs while sending the message, any outstanding work also fails.
     * @param URLSessionWebSocketTaskMessage $message The WebSocket message to send to the other endpoint.
     * @param Closure(Error|null): void $completionHandler A closure that receives an Error that indicates an error encountered while sending, or nil if no error occurred.
     * @throws Exception
     */
    public function send(URLSessionWebSocketTaskMessage $message, Closure $completionHandler): void
    {
        $this->sendBuffer->append([$message, $completionHandler]);
        $this->doPendingWork();
    }

    /**
     * Reads a WebSocket message once all the frames of the message are available.
     *
     * If the task reaches the {@see maximumMessageSize} while buffering the frames, this call fails with an error.
     * @param Closure(URLSessionWebSocketTaskMessage|null, Error|null): void $completionHandler A closure that receives two parameters: the WebSocket message, and an Error that indicates an error encountered while receiving the message. The error is nil if no error occurred.
     * @throws Exception
     */
    public function receive(Closure $completionHandler): void
    {
        $this->receiveCompletionHandlers->append($completionHandler);
        $this->doPendingWork();
    }

    /**
     * Sends a ping frame from the client side, with a closure to receive the pong from the server endpoint.
     *
     * When sending multiple pings, the task always calls pongReceiveHandler in the order it sent the pings.
     * @param Closure(Error|null): void $pongReceiveHandler A closure called by the task when it receives the pong from the server. The closure receives an Error that indicates a lost connection or other problem, or nil if no error occurred.
     * @throws Exception
     */
    public function sendPing(Closure $pongReceiveHandler): void
    {
        $this->getProtocol(function (?URLProtocol $protocol) use ($pongReceiveHandler): void {
            if ($protocol instanceof WebSocketURLProtocol) {
                try {
                    $protocol->sendWebSocketData("", URLSessionWebSocketOperationCode::ping);
                    $this->pongCompletionHandlers->append($pongReceiveHandler);
                } catch (Exception) {
                    $pongReceiveHandler(new Error(URLErrorDomain, URLErrorBadServerResponse));
                }
            } else {
                $pongReceiveHandler(new Error(URLErrorDomain, URLErrorNetworkConnectionLost));
            }
        });
    }

    /**
     * @throws Exception
     */
    public function cancel(): void
    {
        $this->cancelWithReason(URLSessionWebSocketTaskCloseCode::invalid, null);
    }

    /**
     * Sends a close frame with the given close code and optional close reason.
     *
     * If you call {@see cancel()} on the task instead of this method, it sends a cancellation frame with no close code or reason.
     * @param URLSessionWebSocketTaskCloseCode $closeCode A {@see URLSessionWebSocketTaskCloseCode} that indicates the reason for closing the connection.
     * @param string|null $reason Optional further information to explain the closing. The value of this parameter is defined by the endpoints, not by the standard.
     * @throws Exception
     */
    public function cancelWithReason(URLSessionWebSocketTaskCloseCode $closeCode, ?string $reason): void
    {
        $this->close($closeCode, $reason);
    }

    /**
     * @throws Exception
     * @internal
     */
    public function appendReceivedMessage(URLSessionWebSocketTaskMessage $message): void
    {
        $this->receiveBuffer->append($message);
        $this->doPendingWork();
    }

    /**
     * @throws Exception
     * @internal
     */
    public function noteReceivedPong(): void
    {
        if (!($completionHandler = $this->pongCompletionHandlers->popFirst())) {
            $this->close(URLSessionWebSocketTaskCloseCode::protocolError);
            return;
        }
        $completionHandler(null);
    }

    /**
     * @throws Exception
     * @internal
     */
    public function close(URLSessionWebSocketTaskCloseCode $code, ?string $reason = null): void
    {
        if ($this->taskError !== null) {
            return;
        }
        $this->closeCode = $code;
        $this->closeReason = $reason;
        $this->taskError = new Error(URLErrorDomain, URLErrorNetworkConnectionLost);
        $this->closeMessage = [$code, $reason ?? ""];
        $this->doPendingWork();
    }

    /**
     * @throws Exception
     */
    private function doPendingWork(): void
    {
        if ($taskError = $this->taskError ?? $this->error) {
            foreach ($this->sendBuffer as $element) {
                [, $handler] = $element;
                $handler($taskError);
            }
            $this->sendBuffer->removeAll();
            foreach ($this->receiveCompletionHandlers as $receiveCompletionHandler) {
                $receiveCompletionHandler(null, $taskError);
            }
            $this->receiveCompletionHandlers->removeAll();
            $this->getProtocol(function (?URLProtocol $protocol): void {
                if ($this->handshakeCompleted && $protocol instanceof WebSocketURLProtocol) {
                    $this->sendCloseMessage($protocol);
                }
            });
        } else {
            $this->getProtocol(function (?URLProtocol $protocol): void {
                if ($this->handshakeCompleted && $protocol instanceof WebSocketURLProtocol) {
                    while (!$this->sendBuffer->isEmpty()) {
                        /** @var array{URLSessionWebSocketTaskMessage, Closure(Error|null): void} $element */
                        $element = $this->sendBuffer->popFirst();
                        [$message, $completionHandler] = $element;
                        try {
                            switch ($message->rawValue) {
                                case URLSessionWebSocketTaskMessageRawValue::data:
                                    $protocol->sendWebSocketData($message->data, URLSessionWebSocketOperationCode::binary);
                                    break;
                                case URLSessionWebSocketTaskMessageRawValue::string:
                                    $protocol->sendWebSocketData($message->string, URLSessionWebSocketOperationCode::text);
                                    break;
                            }
                            $completionHandler(null);
                        } catch (Exception) {
                        }
                    }
                    $this->sendCloseMessage($protocol);
                }
                while (!$this->receiveBuffer->isEmpty() && !$this->receiveCompletionHandlers->isEmpty()) {
                    /** @var URLSessionWebSocketTaskMessage $message */
                    $message = $this->receiveBuffer->popFirst();
                    /** @var Closure(URLSessionWebSocketTaskMessage|null, Error|null): void $handler */
                    $handler = $this->receiveCompletionHandlers->popFirst();
                    $handler($message, null);
                }
            });
        }
    }

    private function sendCloseMessage(WebSocketURLProtocol $protocol): void
    {
        if (!($closeMessage = $this->closeMessage)) {
            return;
        }
        $this->closeMessage = null;
        try {
            [$code, $reason] = $closeMessage;
            $data = (new ArrayClass(str_split(sprintf('%016b', $code->value), 8)))->map(fn(string $string): string => chr((int)bindec($string)))->join("");
            $data .= $reason;
            $protocol->sendWebSocketData($data, URLSessionWebSocketOperationCode::close);
        } catch (Exception) {
        }
    }

    /**
     * @throws Exception
     */
    public function resume(): void
    {
        if (!EasyHandle::supportsWebSockets()) {
            $userInfo = new Dictionary([LocalizedDescriptionKey => ""]);
            if ($url = $this->originalRequest?->url) {
                $userInfo[URLErrorFailingURLErrorKey] = $url;
            }
            $this->error = new Error(URLErrorDomain, URLErrorUnsupportedURL, $userInfo);
            (new ProtocolClient())->urlProtocolTaskDidFailWithError($this, $this->error);
            return;
        }
        parent::resume();
    }

    /**
     * @internal
     */
    public static function supportsWebSockets(): bool
    {
        return EasyHandle::supportsWebSockets();
    }
}
