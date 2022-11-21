<?php

namespace Sabatier\Foundation\Networking;

/** @internal */
enum EasyHandlePauseState: int
{
    case receivePaused = 1 << 0;
    case sendPaused = 1 << 1;
}
