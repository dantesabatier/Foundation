<?php

namespace Sabatier\Foundation\Networking;

/**
 * An enumeration of the types of messages sent and received.
 */
class URLSessionWebSocketTaskMessage
{
    private function __construct(public readonly URLSessionWebSocketTaskMessageRawValue $rawValue, public readonly ?string $data = null, public readonly ?string $string = null)
    {
    }

    /**
     * A WebSocket message that contains a block of data.
     * @param string $data
     * @return URLSessionWebSocketTaskMessage
     */
    public static function data(string $data): URLSessionWebSocketTaskMessage
    {
        return new URLSessionWebSocketTaskMessage(URLSessionWebSocketTaskMessageRawValue::data, $data);
    }

    /**
     * A WebSocket message that contains a string.
     * @param string $string
     * @return URLSessionWebSocketTaskMessage
     */
    public static function string(string $string): URLSessionWebSocketTaskMessage
    {
        return new URLSessionWebSocketTaskMessage(URLSessionWebSocketTaskMessageRawValue::string, string: $string);
    }
}
