<?php

declare(strict_types=1);

namespace Sabatier\Foundation;

final class KeyedUnarchiver
{
    /**
     * Decodes a previously archived object graph and returns the root object.
     *
     * Decoding instantiates whatever classes the archive names, so an archive a caller does not control is a way to construct arbitrary objects and run their destructors.
     * Pass $allowedClasses whenever the data could have been written by anything other than this process: an archive naming a class outside the list decodes to {@see __PHP_Incomplete_Class} instead of instantiating it.
     * This is what secure coding is in PHP. The framework this ports declares it per class and states the expected class when decoding; PHP has no equivalent of the marker protocol, and does not need one, because the engine checks the list before it constructs anything.
     * @param string $data An object graph previously encoded by {@see KeyedArchiver}.
     * @param ArrayClass<class-string>|null $allowedClasses The classes the archive is permitted to name, or null to permit any. An empty array permits none, so a graph of plain values still decodes while every object in it does not.
     * @return mixed The unarchived object, or the data unchanged if it is not an archive.
     */
    public static function unarchiveTopLevelObjectWithData(string $data, ?ArrayClass $allowedClasses = null): mixed
    {
        $options = $allowedClasses instanceof ArrayClass ? ["allowed_classes" => $allowedClasses->array] : [];
        return unsafe_value(fn(): mixed => ($decoded = base64_decode($data, true)) && is_serialized($decoded) ? unserialize($decoded, $options) : $data);
    }
}
