<?php

namespace Sabatier\Foundation\Networking;

/** @internal */
class EasyHandlePauseState
{
    const receivePaused = 1 << 0;
    const sendPaused = 1 << 1;

    public function __construct(public readonly int $rawValue = 0)
    {
    }

    public function contains(int $v): bool
    {
        return ($this->rawValue & $v) === $v;
    }

    public function insert(int $v): void
    {
        $this->rawValue |= $v;
    }

    public function remove(int $v): void
    {
        $this->rawValue &= ~$v;
    }

    public function setState(EasyHandle $handle): void
    {
        curl_pause($handle->rawHandle, 0 | ($this->rawValue & EasyHandlePauseState::sendPaused ? CURLPAUSE_SEND : CURLPAUSE_SEND_CONT) | ($this->rawValue & EasyHandlePauseState::receivePaused ? CURLPAUSE_RECV : CURLPAUSE_RECV_CONT));
    }
}
