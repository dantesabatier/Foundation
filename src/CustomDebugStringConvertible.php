<?php

namespace Sabatier\Foundation;

/**
 * A type with a customized textual representation suitable for debugging purposes.
 */
interface CustomDebugStringConvertible extends CustomStringConvertible
{
    /**
     * A textual representation of this instance, suitable for debugging.
     */
    public string $debugDescription {
        get;
    }
}
