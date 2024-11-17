<?php

namespace Sabatier\Foundation;

/**
 * An abstract class that declares an interface for objects that create, interpret, and validate the textual representation of values.
 */
abstract class Formatter
{
    /**
     * The default implementation of this method raises an exception.
     * @param mixed $object The object for which a textual representation is returned.
     * @return string|null A string that textually represents object for display. Returns nil if object is not of the correct class.
     */
    abstract public function string(mixed $object): ?string;
}
