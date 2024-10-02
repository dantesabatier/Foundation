<?php

namespace Sabatier\Foundation;

use Closure;
use Override;

/**
 * @property-read bool $isIndeterminate
 * @property-read bool $isFinished
 * @property-read float $fractionCompleted
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
            "fractionCompleted" => $this->isIndeterminate ? 0.0 : ($this->total == 0 ? 1.0 : $this->completed / $this->total),
            default => $this->valueForUndefinedKey($name),
        };
    }

    private static function fromDouble(float $double): array
    {
        $denominator = 131072;
        $numerator = $double / (1.0 / (float)$denominator);
        return [$numerator, $numerator];
    }

    private static function greatestCommonDivisor(float $inA, float $inB): float
    {
        $a = $inA;
        $b = $inB;
        do {
            $tmp = $b;
            $b = $a % $b;
            $a = $tmp;
        } while ($b != 0);
        return $a;
    }

    private static function leastCommonMultiple(float $a, float $b): float
    {
        return $a / self::greatestCommonDivisor($a, $b);
    }

    private static function simplify(float $n, float $d): array
    {
        $gcd = self::greatestCommonDivisor($n, $d);
        return [$n / $gcd, $d / $gcd];
    }

    private function simplified(): ProgressFraction
    {
        [$completed, $total] = self::simplify($this->completed, $this->total);
        return new self($completed, $total);
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

    /**
     * @param ProgressFraction $fraction
     * @param Closure(float, float): float $whichOperator
     * @param Closure(float, float): array{float, boolean} $whichOverflow
     * @return ProgressFraction
     */
    private function math(ProgressFraction $fraction, Closure $whichOperator, Closure $whichOverflow): ProgressFraction
    {
        !($this->total == 0 && $fraction->total == 0) ?: fatal_error("Attempt to add or subtract invalid fraction");
        if ($this->total == 0) {
            return $fraction;
        }
        if ($fraction->total == 0) {
            return $this;
        }
        if ($this->overflowed || $fraction->overflowed) {
            return ProgressFraction::fraction($whichOperator($this->fractionCompleted, $fraction->fractionCompleted), true);
        }
        if ($lcm = self::leastCommonMultiple($this->total, $fraction->total)) {
            return new ProgressFraction($whichOperator($this->completed * ($lcm / $this->total), $fraction->completed * ($lcm / $fraction->total)), $lcm);
        }
        $lhsSimplified = $this->simplified();
        $rhsSimplified = $fraction->simplified();
        if ($lcm = self::leastCommonMultiple($lhsSimplified->total, $rhsSimplified->total)) {
            [$completed, $overflowed] = $whichOverflow($lhsSimplified->completed * ($lcm / $lhsSimplified->total), $rhsSimplified->completed * ($lcm / $rhsSimplified->total));
            return new self($completed, $lcm, $overflowed);
        }
        return ProgressFraction::fraction($whichOperator($this->fractionCompleted, $fraction->fractionCompleted), true);
    }

    #[Override]
    public function isEqual(mixed $other): bool
    {
        return $other instanceof ProgressFraction && $this->total === $other->total && $this->completed === $other->completed;
    }

    public function add(ProgressFraction $addend): ProgressFraction
    {
        /** @psalm-suppress ArgumentTypeCoercion */
        return $this->math($addend, fn(float $l, float $r): float => $l + $r, fn(): array => []);
    }

    public function subtract(ProgressFraction $subtracting): ProgressFraction
    {
        /** @psalm-suppress ArgumentTypeCoercion */
        return $this->math($subtracting, fn(float $l, float $r): float => $l - $r, fn(): array => []);
    }

    public function multiply(ProgressFraction $factor): ProgressFraction
    {
        /** @psalm-suppress ArgumentTypeCoercion */
        return $this->math($factor, fn(float $l, float $r): float => $l * $r, fn(): array => []);
    }

    public function divide(ProgressFraction $divisor): ProgressFraction
    {
        /** @psalm-suppress ArgumentTypeCoercion */
        return $this->math($divisor, fn(float $l, float $r): float => $l / $r, fn(): array => []);
    }

    #[Override]
    public function debugDescription(): string
    {
        return "$this->completed / $this->total ($this->fractionCompleted)";
    }
}
