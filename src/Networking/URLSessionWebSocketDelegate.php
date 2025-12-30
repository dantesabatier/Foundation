<?php

namespace Sabatier\Foundation\Networking;

/**
 * A protocol that defines methods that URL session instances call on their delegates to handle task-level events specific to WebSocket tasks.
 */
interface URLSessionWebSocketDelegate extends URLSessionTaskDelegate
{
    /**
     * Tells the delegate that the WebSocket task successfully negotiated the handshake with the endpoint, indicating the negotiated protocol.
     * 
     * If the handshake fails, the task doesn't call this delegate method.
     @param URLSession $session The session of the WebSocket task that opened.
     @param URLSessionWebSocketTask $webSocketTask The WebSocket task that opened.
     @param string|null $protocol The protocol picked during the handshake phase. This parameter is null if the server did not pick a protocol or if the client did not advertise protocols when creating the task.
     * 
     */
    public function urlSessionWebSocketTaskDidOpenWithProtocol(URLSession $session, URLSessionWebSocketTask $webSocketTask, ?string $protocol): void;

    /**
     * Tells the delegate that the WebSocket task received a close frame from the server endpoint, optionally including a close code and reason from the server.
     * 
     @param URLSession $session The session of the WebSocket task that closed.
     @param URLSessionWebSocketTask $webSocketTask The WebSocket task that closed.
     @param URLSessionWebSocketTaskCloseCode $closeCode The close code provided by the server. If the close frame didn't include a close code, this value is null.
     @param string|null $reason The close reason provided by the server. If the close frame didn't include a reason, this value is null.
     */
    public function urlSessionWebSocketTaskDidCloseWithReason(URLSession $session, URLSessionWebSocketTask $webSocketTask, ?URLSessionWebSocketTaskCloseCode $closeCode, ?string $reason): void;
}
