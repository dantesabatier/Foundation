<?php

namespace Sabatier\Foundation\Networking;

/** @internal */
class EasyHandleProgress
{
    public function __construct(public readonly float $totalBytesSent, public readonly float $totalBytesExpectedToSend, public readonly float $totalBytesReceived, public readonly float $totalBytesExpectedToReceive)
    {
    }
}
