<?php

namespace Sabatier\Foundation;

use Sabatier\Foundation\Dictionary;

interface Representable
{
    /**
     * Returns a dictionary defining the default structural representation
     * of the object.
     *
     * This representation serves as a safe fallback when no explicit
     * serialization configuration is provided.
     */
    public static function defaultRepresentation(): Dictionary;
}
