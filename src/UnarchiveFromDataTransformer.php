<?php

namespace Sabatier\Foundation;

use Exception;
use Override;

/** @internal */
class UnarchiveFromDataTransformer extends SharedValueTransformer
{
    /**
     * @throws Exception
     */
    #[Override]
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
    #[Override]
    public function reverseTransformedValue(mixed $value): mixed
    {
        if (is_string($value)) {
            return KeyedUnarchiver::unarchiveTopLevelObjectWithData($value);
        }
        return $value;
    }

    #[Override]
    public function description(): string
    {
        return "<shared UnarchiveFromData transformer>";
    }
}
