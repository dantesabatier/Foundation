<?php

namespace Sabatier\Foundation\Networking;

use Closure;
use Sabatier\Foundation\Error;

/**
 * A URL session task that communicates over the WebSockets protocol standard.
 */
class URLSessionWebSocketTask extends URLSessionTask
{
    /** @var int The maximum number of bytes to buffer before the receive call fails with an error. This value includes the sum of all bytes from continuation frames. Receive calls will fail once the task reaches this limit. */
    public int $maximumMessageSize = 0;
    /** @var URLSessionWebSocketTaskCloseCode A code that indicates the reason a connection closed. */
    public readonly URLSessionWebSocketTaskCloseCode $closeCode;
    /** @var string|null A block of data that provides further information about why a connection closed. */
    public readonly ?string $closeReason;
    /** @internal */
    public ?string $protocolPicked = null;
    /** @internal */
    public bool $handshakeCompleted = false;

    /**
     * Sends a WebSocket message, receiving the result in a completion handler.
     *
     * If an error occurs while sending the message, any outstanding work also fails.
     * @param URLSessionWebSocketTaskMessage $message The WebSocket message to send to the other endpoint.
     * @param Closure(Error|null): void $completionHandler A closure that receives an Error that indicates an error encountered while sending, or nil if no error occurred.
     */
    public function send(URLSessionWebSocketTaskMessage $message, Closure $completionHandler): void
    {
    }

    /**
     * Reads a WebSocket message once all the frames of the message are available.
     *
     * If the task reaches the {@see maximumMessageSize} while buffering the frames, this call fails with an error.
     * @param Closure(URLSessionWebSocketTaskMessage $message, Error|null): void $completionHandler A closure that receives two parameters: the WebSocket message, and an Error that indicates an error encountered while receiving the message. The error is nil if no error occurred.
     */
    public function receive(Closure $completionHandler): void
    {
    }

    /**
     * Sends a ping frame from the client side, with a closure to receive the pong from the server endpoint.
     *
     * When sending multiple pings, the task always calls pongReceiveHandler in the order it sent the pings.
     * @param Closure(Error|null): void $pongReceiveHandler A closure called by the task when it receives the pong from the server. The closure receives an Error that indicates a lost connection or other problem, or nil if no error occurred.
     */
    public function sendPing(Closure $pongReceiveHandler): void
    {
    }

    /**
     * Sends a close frame with the given close code and optional close reason.
     *
     * If you call {@see cancel()} on the task instead of this method, it sends a cancellation frame with no close code or reason.
     * @param URLSessionWebSocketTaskCloseCode $closeCode A {@see URLSessionWebSocketTaskCloseCode} that indicates the reason for closing the connection.
     * @param string|null $reason Optional further information to explain the closing. The value of this parameter is defined by the endpoints, not by the standard.
     */
    public function cancelWithReason(URLSessionWebSocketTaskCloseCode $closeCode, ?string $reason): void
    {
    }
}
