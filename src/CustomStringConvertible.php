<?php

namespace Sabatier\Foundation;

use Stringable;

/**
 * Interface CustomStringConvertible
 * A type with a customized textual representation.
 * @package Sabatier\Foundation
 */
interface CustomStringConvertible extends Stringable
{
    /**
     * A textual representation of this instance.
     * @return string
     */
    public function description(): string;
}
