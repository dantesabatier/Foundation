<?php

declare(strict_types=1);

namespace Sabatier\Foundation\Networking;

/** @internal */
enum DataDrainRawValue
{
    case inMemory;
    case toFile;
    case ignore;
}
