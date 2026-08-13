<?php

declare(strict_types=1);

namespace Sabatier\Foundation;

use stdClass;

/** @internal */
interface ArrayConversionStrategy
{
    public ArrayConversionStrategy $nestedConversionStrategy {
        get;
    }

    /**
     * Converts a decoded value into its collection counterpart.
     *
     * Takes a `stdClass` as readily as an array so that the distinction JSON draws between `{}`
     * and `[]` survives: a `stdClass` becomes a `Dictionary` even when empty, a list an
     * `ArrayClass`. `is_sequential()` cannot tell those apart on its own, because
     * `json_decode($json, true)` renders an empty object and an empty list as the same empty
     * array. Only `stdClass` is decoded this way — any other object is a value in its own right
     * and is stored as it is, not taken apart into its properties.
     *
     * @param array<array-key, mixed>|stdClass $value The decoded value to convert.
     * @param bool $preserveNull Whether a `null` element is preserved as `Nil` rather than dropped.
     */
    public function convert(array|stdClass $value, bool $preserveNull = true): mixed;
}
