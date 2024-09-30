<?php

namespace Sabatier\Foundation;

use Override;

/** @internal */
class NegateBooleanTransformer extends SharedValueTransformer
{
    #[Override]
    public static function transformedValueClass(): string
    {
        return Number::class;
    }

    /** @noinspection PhpMixedReturnTypeCanBeReducedInspection */
    #[Override]
    public function transformedValue(mixed $value): mixed
    {
        assert($value instanceof Number);
        return new Number(!$value->boolValue);
    }

    #[Override]
    public function description(): string
    {
        return "<shared NegateBoolean transformer>";
    }
}
