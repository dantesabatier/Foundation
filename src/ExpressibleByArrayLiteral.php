<?php

declare(strict_types=1);

namespace Sabatier\Foundation;

/**
 * A type that can be initialized using an array literal.
 */
interface ExpressibleByArrayLiteral
{
    public array $array {
        get;
    }
}
