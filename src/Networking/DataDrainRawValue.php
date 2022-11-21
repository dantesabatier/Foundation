<?php

namespace Sabatier\Foundation\Networking;

/** @internal */
enum DataDrainRawValue
{
    case inMemory;
    case toFile;
    case ignore;
}
