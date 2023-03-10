<?php

namespace Sabatier\Foundation\Networking;

/** @internal */
class EasyHandlePauseState
{
    const receivePaused = 1 << 0;
    const sendPaused = 1 << 1;
}
