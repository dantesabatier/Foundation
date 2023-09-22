<?php

namespace Sabatier\Foundation;

class KeyedArchiver
{
    /**
     * Encodes an object graph with the given root object into a data representation, optionally requiring secure coding.
     * @param mixed $object The root of the object graph to archive.
     * @param bool $requiresSecureCoding A Boolean value indicating whether all encoded objects must conform to SecureCoding.
     */
    public static function archivedData(/** @noinspection PhpUnusedParameterInspection */ mixed $object, bool $requiresSecureCoding = true): string
    {
        $decoded = is_string($object) ? base64_decode($object, true) : false;
        return unsafe_value(fn(): string => !$decoded || !is_serialized($decoded) ? base64_encode(serialize($object)) : $object);
    }
}
