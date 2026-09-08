<?php

declare(strict_types=1);

namespace Sabatier\Foundation;

use JetBrains\PhpStorm\ArrayShape;
use JetBrains\PhpStorm\Pure;
use Override;

/**
 * Wraps a PHP value with Foundation comparison, serialization, and display behavior.
 */
class Value extends ObjectClass
{
    /** @var mixed|null The wrapped value after recognized string literals are normalized. */
    public readonly mixed $value;
    /** @var string The debug type of the normalized value. */
    public readonly string $type;
    #[Override]
    public string $description {
        get => human_readable_value($this->value);
    }
    #[Override]
    public string $debugDescription {
        get => sprintf("<%s %s> (%s)%s", $this->class, $this->hash, $this->type, $this->description);
    }

    /**
     * Wraps a value, converting recognized null, Boolean, and numeric strings to their scalar forms.
     * @param mixed $value The value or scalar literal to wrap.
     */
    public function __construct(mixed $value)
    {
        if (is_string($value)) {
            if ((string_is_equal($value, "NULL", CompareOptions::caseInsensitive) || string_is_equal($value, "NIL", CompareOptions::caseInsensitive))) {
                $value = null;
            } elseif (string_is_equal($value, "TRUE", CompareOptions::caseInsensitive) || string_is_equal($value, "FALSE", CompareOptions::caseInsensitive) || string_is_equal($value, "YES", CompareOptions::caseInsensitive) || string_is_equal($value, "NO", CompareOptions::caseInsensitive)) {
                $value = filter_var($value, FILTER_VALIDATE_BOOLEAN);
            } elseif (is_numeric($value)) {
                $value = str_contains($value, ".") || string_contains($value, "e", CompareOptions::caseInsensitive) ? (float)$value : (int)$value;
            }
        } elseif ($value instanceof Value) {
            $value = $value->value;
        }
        $this->value = $value;
        $this->type = typeof($this->value);
    }

    /** @return array{value: mixed} */
    #[Pure]
    #[ArrayShape(["value" => "mixed"])]
    public function __serialize(): array
    {
        return ["value" => $this->value];
    }

    /** @param array{value: mixed} $data */
    public function __unserialize(array $data): void
    {
        $this->value = $data["value"];
        $this->type = typeof($this->value);
    }

    #[Override]
    public function compare(mixed $other): ComparisonResult
    {
        if (is_scalar($other) || is_null($other)) {
            return ComparisonResult::from(($this->type !== typeof($other)) ? -1 : $this->value <=> $other);
        }
        if ($other instanceof Value) {
            return ComparisonResult::from($this->value <=> $other->value);
        }
        return ComparisonResult::orderedDescending;
    }

    #[Override]
    public function isEqual(mixed $other): bool
    {
        return $this->compare($other) === ComparisonResult::orderedSame;
    }

    #[Pure]
    #[Override]
    public function jsonSerialize(): mixed
    {
        return $this->value;
    }
}
