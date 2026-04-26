<?php

declare(strict_types=1);

namespace Sabatier\Foundation\Networking;

/** @internal */
enum ParsedResponseHeaderRawVale
{
    case partial;
    case complete;
}
