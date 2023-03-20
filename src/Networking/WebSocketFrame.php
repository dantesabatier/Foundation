<?php

namespace Sabatier\Foundation\Networking;

readonly class WebSocketFrame
{
    public function __construct(public string $payload, public URLSessionWebSocketOperationCode $operationCode = URLSessionWebSocketOperationCode::cont, public bool $isFinal = false, public bool $isMasked = true)
    {
    }
}
