<?php

namespace Sabatier\Foundation;

/**
 * Defines a contract for objects that can provide a default representation.
 */
interface Representable
{
    /**
     * Provides the default representation of the object as a Dictionary.
     *
     * @return Dictionary<mixed> The default representation of the object.
     */
    public static function defaultRepresentation(): Dictionary;
}
