<?php

namespace Sabatier\Foundation\Networking;

/** @internal */
enum URLSessionWebSocketOperationCode: int
{
    case cont = 0;
    case text = 1;
    case binary = 2;
    case close = 8;
    case ping = 9;
    case pong = 10;
}
