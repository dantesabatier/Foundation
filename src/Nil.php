<?php

namespace Sabatier\Foundation;

/**
 * A singleton object used to represent null values in collection objects that don't allow null values.
 */
final class Nil extends Value
{
    private static ?Nil $nil = null;

    private function __construct()
    {
        parent::__construct(null);
    }

    /**
     * Returns the singleton instance of Nil.
     */
    public static function nil(): Nil
    {
        if (self::$nil === null) {
            self::$nil = new Nil();
        }
        return self::$nil;
    }
}
