<?php

declare(strict_types=1);

namespace Sabatier\Foundation;

final class KeyedArchiver
{
    /**
     * Encodes an object graph with the given root object into a data representation.
     *
     * Encoding goes through PHP's own {@see serialize()}, which places no requirement on the classes in the graph.
     * @param mixed $object The root of the object graph to archive.
     * @param bool $requiresSecureCoding Ignored. The parameter is kept for signature compatibility with the framework this ports; there is no SecureCoding protocol here, and no value of it changes what is written.
     * Security belongs to the decoding side: pass $allowedClasses to {@see KeyedUnarchiver::unarchiveTopLevelObjectWithData()} when reading an archive this process did not write.
     */
    public static function archivedData(/** @noinspection PhpUnusedParameterInspection */ mixed $object, bool $requiresSecureCoding = true): string
    {
        $decoded = is_string($object) ? base64_decode($object, true) : false;
        return unsafe_value(fn(): string => !$decoded || !is_serialized($decoded) ? base64_encode(serialize($object)) : $object);
    }
}
