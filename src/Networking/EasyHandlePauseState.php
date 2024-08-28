<?php

namespace Sabatier\Foundation\Networking;

use CurlHandle;
use Sabatier\Foundation\OptionSet;

/** @internal */
class EasyHandlePauseState extends OptionSet
{
    final const int receivePaused = 1 << 0;
    final const int sendPaused = 1 << 1;

    public function setState(EasyHandle $handle): void
    {
        if ($handle->rawHandle instanceof CurlHandle) {
            curl_pause($handle->rawHandle, 0 | ($this->contains(EasyHandlePauseState::sendPaused) ? CURLPAUSE_SEND : CURLPAUSE_SEND_CONT) | ($this->contains(EasyHandlePauseState::receivePaused) ? CURLPAUSE_RECV : CURLPAUSE_RECV_CONT));
        }
    }
}
