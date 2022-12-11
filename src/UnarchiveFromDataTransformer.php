<?php

namespace Sabatier\Foundation;

use Exception;

/** @internal */
class UnarchiveFromDataTransformer extends SharedValueTransformer
{
    /**
     * @throws Exception
     */
    public function transformedValue(mixed $value): ?string
    {
        if ($value === null) {
            return null;
        }
        return KeyedArchiver::archivedData($value);
    }

    /**
     * @throws Exception
     */
    public function reverseTransformedValue(mixed $value): mixed
    {
        return is_string($value) ? KeyedUnarchiver::unarchiveTopLevelObjectWithData($value) : $value;
    }

    public function description(): string
    {
        return "<shared UnarchiveFromData transformer>";
    }
}
