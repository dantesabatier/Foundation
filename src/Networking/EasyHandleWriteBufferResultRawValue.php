<?php

namespace Sabatier\Foundation\Networking;

/** @internal */
enum EasyHandleWriteBufferResultRawValue: int
{
    case abort = 0;
    case pause = 1;
    case bytes = 2;
}
