<?php

declare(strict_types=1);

namespace Sabatier\Foundation\Networking;

/** @internal */
final readonly class EasyHandleProgress
{
    public function __construct(public float $totalBytesSent, public float $totalBytesExpectedToSend, public float $totalBytesReceived, public float $totalBytesExpectedToReceive)
    {
    }
}
