<?php

namespace Sabatier\Foundation;

/**
 * An object wrapper for primitive scalar numeric values.
 *
 * Number is a subclass of Value that offers a value as any scalar (numeric) type. It defines a set of methods specifically for setting and accessing the value as an int, float, double or as a BOOL. (Note that number objects do not necessarily preserve the type they are created with.) It also defines a {@see Comparable::compare()} method to determine the ordering of two Number objects.
 */
class Number extends Value
{
    /** @var bool The number object's value expressed as a Boolean value. A 0 value always means false, and any nonzero value is interpreted as true. */
    public readonly bool $boolValue;
    /** @var float The number object's value expressed as a float, converted as necessary. */
    public readonly float $floatValue;
    /** @var float The number object's value expressed as a double, converted as necessary. */
    public readonly float $doubleValue;
    /** @var int The number object's value expressed as int, converted as necessary. */
    public readonly int $intValue;
    /** @var string The number object's value expressed as a human-readable string. */
    public readonly string $stringValue;

    /**
     * Returns a Number object initialized to contain a given value.
     * @param scalar $value The value for the new number.
     */
    public function __construct(bool|float|int|string $value)
    {
        parent::__construct($value);
        assert(is_numeric($this->value) || is_bool($this->value), sprintf("invalid argument, expecting a numeric value, (%s)%s given", typeof($this->value), human_readable_value($this->value)));
        unset($this->boolValue);
        unset($this->floatValue);
        unset($this->doubleValue);
        unset($this->intValue);
        unset($this->stringValue);
    }

    public function __get(string $name)
    {
        return $this->$name = match ($name) {
            'boolValue' => (bool) $this->value,
            'floatValue', 'doubleValue' => (float) $this->value,
            'intValue' => (int) $this->value,
            'stringValue' => human_readable_value($this->value),
            default => $this->valueForUndefinedKey($name)
        };
    }

    /**
     * Returns an {@see ComparisonResult} value that indicates whether the number object's value is greater than, equal to, or less than a given number.
     *
     * The compare() method follows the standard C rules for type conversion. For example, if you compare a Number object that has an integer value with a Number object that has a floating point value, the integer value is converted to a floating-point value for comparison.
     * @param mixed $other The number to compare to the number object's value.
     * This value must not be nil.
     * @return ComparisonResult {@see ComparisonResult::orderedAscending} if the value of $other is greater than the number object's, {@see ComparisonResult::orderedSame} if they're equal, and {@see ComparisonResult::orderedDescending} if the value of $other is less than the number object's.
     */
    public function compare(mixed $other): ComparisonResult
    {
        if (is_int($other)) {
            return ComparisonResult::from($this->intValue <=> $other);
        } elseif (is_float($other)) {
            return ComparisonResult::from($this->floatValue <=> $other);
        } elseif (is_bool($other)) {
            return ComparisonResult::from($this->boolValue <=> $other);
        } elseif (is_string($other)) {
            return ComparisonResult::from(string_compare($this->stringValue, $other));
        } elseif ($other instanceof Number) {
            return $this->compare($other->value);
        }
        return parent::compare($other);
    }

    /**
     * Returns a Boolean value that indicates whether the number object's value and a given number are equal.
     *
     * Two Number objects are considered equal if they have the same id values or if they have equivalent values (as determined by the {@see compare()} method).
     * This method is more efficient than {@see compare()} if you know the two objects are numbers.
     * @param mixed $other The number to compare to the number object's value.
     * @return bool true if the number object's value and aNumber are equal, otherwise false.
     */
    public function isEqual(mixed $other): bool
    {
        return $this->compare($other) === ComparisonResult::orderedSame;
    }
}
