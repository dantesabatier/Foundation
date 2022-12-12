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
        if (is_string($value)) {
            return KeyedUnarchiver::unarchiveTopLevelObjectWithData($value);
        }
        return $value;
    }

    public function description(): string
    {
        return "<shared UnarchiveFromData transformer>";
    }
}
