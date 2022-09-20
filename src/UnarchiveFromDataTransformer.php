<?php

namespace Sabatier\Foundation;

/** @internal */
class UnarchiveFromDataTransformer extends SharedValueTransformer
{
    public function transformedValue(mixed $value): mixed
    {
        if ($value === null) {
            return null;
        }
        if (!is_serialized($value)) {
            return serialize($value);
        }
        return $value;
    }

    public function reverseTransformedValue(mixed $value): mixed
    {
        if (is_serialized($value)) {
            return unserialize($value);
        }
        return $value;
    }

    public function description(): string
    {
        return "<shared UnarchiveFromData transformer>";
    }
}
