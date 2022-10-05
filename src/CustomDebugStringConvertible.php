<?php

namespace Sabatier\Foundation;

/**
 * Interface CustomDebugStringConvertible
 * A type with a customized textual representation suitable for debugging purposes.
 * @package Sabatier\Foundation
 */
interface CustomDebugStringConvertible extends CustomStringConvertible
{
    /**
     * A textual representation of this instance, suitable for debugging.
     */
    public function debugDescription(): string;
}
