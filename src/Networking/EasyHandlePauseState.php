<?php

namespace Sabatier\Foundation\Networking;

use Sabatier\Foundation\OptionSet;

/** @internal */
class EasyHandlePauseState extends OptionSet
{
    final const receivePaused = 1 << 0;
    final const sendPaused = 1 << 1;

    public function setState(EasyHandle $handle): void
    {
        curl_pause($handle->rawHandle, 0 | ($this->contains(EasyHandlePauseState::sendPaused) ? CURLPAUSE_SEND : CURLPAUSE_SEND_CONT) | ($this->contains(EasyHandlePauseState::receivePaused) ? CURLPAUSE_RECV : CURLPAUSE_RECV_CONT));
    }
}
