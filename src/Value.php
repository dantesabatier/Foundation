<?php

namespace Sabatier\Foundation;

use JetBrains\PhpStorm\ArrayShape;
use JetBrains\PhpStorm\Pure;

/**
 * Class Value
 * A simple container for a single data item.
 * @package Sabatier\Foundation
 */
class Value extends ObjectClass
{
    /** @var mixed|null The value of the object. */
    public readonly mixed $value;
    /** @var string The type of the value */
    public readonly string $type;

    /**
     * Initializes a value object to contain the specified value.
     * @param mixed $value The value of the object.
     */
    public function __construct(mixed $value)
    {
        if (is_string($value)) {
            if ((string_is_equal($value, "NULL", CompareOptions::caseInsensitive) || string_is_equal($value, "NIL", CompareOptions::caseInsensitive))) {
                $value = null;
            } elseif (string_is_equal($value, "TRUE", CompareOptions::caseInsensitive) || string_is_equal($value, "FALSE", CompareOptions::caseInsensitive) || string_is_equal($value, "YES", CompareOptions::caseInsensitive) || string_is_equal($value, "NO", CompareOptions::caseInsensitive)) {
                $value = filter_var($value, FILTER_VALIDATE_BOOLEAN);
            } elseif (is_numeric($value)) {
                if (string_contains($value, '.')) {
                    $value = filter_var($value, FILTER_VALIDATE_FLOAT);
                } else {
                    $value = filter_var($value, FILTER_VALIDATE_INT);
                }
            }
        } elseif ($value instanceof Value) {
            $value = $value->value;
        }
        $this->value = $value;
        $this->type = typeof($this->value);
    }

    #[Pure]
    #[ArrayShape(['value' => "mixed"])]
    public function __serialize(): array
    {
        return ['value' => $this->value];
    }

    public function __unserialize(array $data): void
    {
        $this->value = $data['value'];
    }

    public function compare(mixed $other): ComparisonResult
    {
        if (is_scalar($other) || is_null($other)) {
            return ComparisonResult::from(($this->type !== typeof($other)) ? -1 : $this->value <=> $other);
        } elseif ($other instanceof Value) {
            return ComparisonResult::from($this->value <=> $other->value);
        }
        return ComparisonResult::orderedDescending;
    }

    public function isEqual(mixed $other): bool
    {
        return $this->compare($other) === ComparisonResult::orderedSame;
    }

    #[Pure]
    public function jsonSerialize(): mixed
    {
        return $this->value;
    }

    public function description(): string
    {
        return human_readable_value($this->value);
    }

    public function debugDescription(): string
    {
        return sprintf("<%s %s> (%s)%s", static::class, $this->hash(), $this->type, $this->description());
    }
}
