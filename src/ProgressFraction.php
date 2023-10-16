<?php

namespace Sabatier\Foundation;

/**
 * @property-read bool $isIndeterminate
 * @property-read bool $isFinished
 * @property-read float $fractionCompleted
 * @property-read bool $isNaN
 * @internal
 */
class ProgressFraction extends ObjectClass
{
    public function __construct(public float $completed = 0.0, public float $total = 0.0, public readonly bool $overflowed = false)
    {
    }

    public function __get(string $name)
    {
        return match ($name) {
            "isIndeterminate" => $this->completed < 0 || $this->total < 0 || ($this->completed == 0 && $this->total == 0),
            "isFinished" => (($this->completed >= $this->total) && $this->completed > 0 && $this->total > 0) || ($this->completed > 0 && $this->total == 0),
            "fractionCompleted" => (function (): float {
                if ($this->isIndeterminate) {
                    return 0.0;
                } else if ($this->total == 0) {
                    return 1.0;
                } else {
                    return $this->completed / $this->total;
                }
            })(),
            "isNaN" => $this->total == 0,
            default => $this->valueForUndefinedKey($name),
        };
    }

    private static function fromDouble(float $double): array
    {
        $denominator = 131072;
        $numerator = $double / (1.0 / (float)$denominator);
        return [$numerator, $numerator];
    }

    public static function fraction(float $double, bool $overflowed = false): ProgressFraction
    {
        $fraction = new self();
        [$completed, $total] = self::fromDouble($double);
        $fraction->completed = $completed;
        $fraction->total = $total;
        $fraction->overflowed = $overflowed;
        return $fraction;
    }

    public function add(/** @noinspection PhpUnusedParameterInspection */ ProgressFraction $addend): ProgressFraction
    {
        unimplemented($this, __FUNCTION__);
    }

    public function subtract(/** @noinspection PhpUnusedParameterInspection */ ProgressFraction $subtracting): ProgressFraction
    {
        unimplemented($this, __FUNCTION__);
    }

    public function multiply(/** @noinspection PhpUnusedParameterInspection */ ProgressFraction $factor): ProgressFraction
    {
        unimplemented($this, __FUNCTION__);
    }

    public function divide(/** @noinspection PhpUnusedParameterInspection */ ProgressFraction $divisor): ProgressFraction
    {
        unimplemented($this, __FUNCTION__);
    }

    public function debugDescription(): string
    {
        return "$this->completed / $this->total ($this->fractionCompleted)";
    }
}
