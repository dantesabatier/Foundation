<?php

namespace Sabatier\Foundation;

use Exception;

class KeyedArchiver
{
    /**
     * Encodes an object graph with the given root object into a data representation, optionally requiring secure coding.
     * @param mixed $object The root of the object graph to archive.
     * @param bool $requiresSecureCoding A Boolean value indicating whether all encoded objects must conform to SecureCoding.
     * @throws Exception
     */
    public static function archivedData(/** @noinspection PhpUnusedParameterInspection */ mixed $object, bool $requiresSecureCoding = true): string
    {
        $decoded = base64_decode($object, true);
        return unsafe_value(fn(): string => !$decoded || !is_serialized($decoded) ? base64_encode(serialize($object)) : $object);
    }
}
