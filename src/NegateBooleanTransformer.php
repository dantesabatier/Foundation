<?php

namespace Sabatier\Foundation;

/** @internal */
class NegateBooleanTransformer extends SharedValueTransformer
{
    public static function transformedValueClass(): string
    {
        return Number::class;
    }

    public function transformedValue(mixed $value): mixed
    {
        assert($value instanceof Number);
        return new Number(!$value->boolValue);
    }

    public function description(): string
    {
        return "<shared NegateBoolean transformer>";
    }
}
