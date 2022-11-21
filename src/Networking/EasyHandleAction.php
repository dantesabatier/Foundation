<?php

namespace Sabatier\Foundation\Networking;

/** @internal */
enum EasyHandleAction: int
{
    case abort = 0;
    case proceed = 1;
    case pause = 2;
}
