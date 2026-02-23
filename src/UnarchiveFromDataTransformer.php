<?php

namespace Sabatier\Foundation;

use Exception;
use Override;

/** @internal */
final class UnarchiveFromDataTransformer extends SharedValueTransformer
{
    #[Override]
    public string $description {
        get => "<shared UnarchiveFromData transformer>";
    }

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
}
