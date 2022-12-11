<?php

namespace Sabatier\Foundation;

use Exception;

class KeyedUnarchiver
{
    /**
     * Decodes a previously-archived object graph, and returns the root object.
     * @param string $data An object graph previously encoded by {@see KeyedArchiver}.
     * @return mixed The unarchived object, or nil if an error occurred.
     * @throws Exception This method throws an error if data does not contain valid keyed data.
     */
    public static function unarchiveTopLevelObjectWithData(string $data): mixed
    {
        return unsafe_value(fn(): mixed => ($decoded = base64_decode($data, true)) && is_serialized($decoded) ? unserialize($decoded) : $data);
    }
}
