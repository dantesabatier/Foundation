<?php

namespace Sabatier\Foundation\Networking;

/**
 * An enumeration of the types of messages sent and received.
 */
readonly class URLSessionWebSocketTaskMessage
{
    private function __construct(public URLSessionWebSocketTaskMessageRawValue $rawValue, public ?string $data = null, public ?string $string = null)
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
