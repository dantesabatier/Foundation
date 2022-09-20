<?php

namespace Sabatier\Foundation;

use Exception;

class KeyedArchiver
{
    /**
     * Encodes an object graph with the given root object into a data representation, optionally requiring secure coding.
     * @param mixed $object The root of the object graph to archive.
     * @param bool $requiresSecureCoding A Boolean value indicating whether all encoded objects must conform to SecureCoding.
     * @return string
     * @throws Exception
     */
    public static function archivedData(/** @noinspection PhpUnusedParameterInspection */ mixed $object, bool $requiresSecureCoding = true): string
    {
        return unsafe_value(fn(): string => serialize($object));
    }
}