<?php

declare(strict_types=1);

namespace Sabatier\Foundation;

use Stringable;

/**
 * A type with a customized textual representation.
 */
interface CustomStringConvertible extends Stringable
{
    /**
     * A textual representation of this instance.
     */
    public string $description {
        get;
    }
}
