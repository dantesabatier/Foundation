<?php

namespace Sabatier\Foundation;

class KeyedUnarchiver
{
    /**
     * Decodes a previously archived object graph and returns the root object.
     * @param string $data An object graph previously encoded by {@see KeyedArchiver}.
     * @return mixed The unarchived object, or null if an error occurred.
     */
    public static function unarchiveTopLevelObjectWithData(string $data): mixed
    {
        return unsafe_value(fn(): mixed => ($decoded = base64_decode($data, true)) && is_serialized($decoded) ? unserialize($decoded) : $data);
    }
}
