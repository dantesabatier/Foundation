<?php

namespace Sabatier\Foundation;

use JetBrains\PhpStorm\Deprecated;

/**
 * A type that can be initialized using an array literal.
 */
interface ExpressibleByArrayLiteral
{
    public array $array {
        get;
    }
}
