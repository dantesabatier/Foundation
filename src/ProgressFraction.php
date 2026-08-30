<?php

declare(strict_types=1);

namespace Sabatier\Foundation;

use Closure;
use Override;

/** @internal */
final class ProgressFraction extends ObjectClass
{
    public bool $isIndeterminate {
        get => $this->completed < 0 || $this->total < 0 || ($this->completed == 0 && $this->total == 0);
    }
    public bool $isFinished {
        get => (($this->completed >= $this->total) && $this->completed > 0 && $this->total > 0) || ($this->completed > 0 && $this->total == 0);
    }
    public float $fractionCompleted {
        get {
            if ($this->isIndeterminate) {
                return 0.0;
            }
            if ($this->total == 0) {
                return 1.0;
            }
            return ($this->completed / $this->total);
        }
    }
    #[Override]
    public string $debugDescription {
        get => "$this->completed / $this->total ($this->fractionCompleted)";
    }

    public function __construct(public float $completed = 0.0, public float $total = 0.0, public bool $overflowed = false)
    {
    }

    private static function fromDouble(float $double): array
    {
        $denominator = 131072.0;
        $numerator = $double / (1.0 / $denominator);
        return [$numerator, $denominator];
    }

    private static function greatestCommonDivisor(float $inA, float $inB): float
    {
        $a = $inA;
        $b = $inB;
        while ($b != 0) {
            $tmp = $b;
            $b = fmod($a, $b);
            $a = $tmp;
        }
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
     * @param Closure(float, float): array{float, bool} $whichOverflow
     * @return ProgressFraction
     */
    private function math(ProgressFraction $fraction, Closure $whichOperator, Closure $whichOverflow): ProgressFraction
    {
        $this->total != 0 || $fraction->total != 0 ?: fatal_error("Attempt to add or subtract invalid fraction");
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

    /**
     * Detects a non-finite result of a checked float operation, mirroring the overflow
     * reporting of the original integer-based fraction arithmetic.
     * @param float $result
     * @return array{float, bool}
     */
    private static function overflowChecked(float $result): array
    {
        return is_finite($result) ? [$result, false] : [$result, true];
    }

    public function add(ProgressFraction $addend): ProgressFraction
    {
        /** @psalm-suppress ArgumentTypeCoercion */
        return $this->math($addend, fn(float $l, float $r): float => $l + $r, fn(float $l, float $r): array => self::overflowChecked($l + $r));
    }

    public function subtract(ProgressFraction $subtracting): ProgressFraction
    {
        /** @psalm-suppress ArgumentTypeCoercion */
        return $this->math($subtracting, fn(float $l, float $r): float => $l - $r, fn(float $l, float $r): array => self::overflowChecked($l - $r));
    }

    public function multiply(ProgressFraction $factor): ProgressFraction
    {
        if ($this->total == 0 || $factor->total == 0) {
            return new ProgressFraction();
        }
        if ($this->overflowed || $factor->overflowed) {
            return ProgressFraction::fraction($this->fractionCompleted * $factor->fractionCompleted, true);
        }
        [$completed, $completedOverflow] = self::overflowChecked($this->completed * $factor->completed);
        [$total, $totalOverflow] = self::overflowChecked($this->total * $factor->total);
        return new ProgressFraction($completed, $total, $completedOverflow || $totalOverflow);
    }

    public function divide(ProgressFraction $divisor): ProgressFraction
    {
        if ($this->total == 0 || $divisor->total == 0 || $divisor->completed == 0) {
            return new ProgressFraction();
        }
        if ($this->overflowed || $divisor->overflowed) {
            return ProgressFraction::fraction($this->fractionCompleted / $divisor->fractionCompleted, true);
        }
        [$completed, $completedOverflow] = self::overflowChecked($this->completed * $divisor->total);
        [$total, $totalOverflow] = self::overflowChecked($this->total * $divisor->completed);
        return new ProgressFraction($completed, $total, $completedOverflow || $totalOverflow);
    }
}
