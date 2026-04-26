<?php

declare(strict_types=1);

namespace Sabatier\Foundation\Networking;

/** @internal */
enum TaskBodyRawValue: int
{
    case none = 0;
    case data = 1;
    case file = 2;
    case stream = 3;
}
