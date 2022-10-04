<?php

namespace Sabatier\Foundation;

use Closure;

/**
 * @psalm-require-implements Sequence
 */
trait SequenceAlgorithms
{
    public function contains(Closure $predicate): bool
    {
        foreach ($this as $i => $e) {
            if ($predicate($e, $i)) {
                return true;
            }
        }
        return false;
    }

    public function containsElement(mixed $element): bool
    {
        return $this->contains(fn (mixed $e): bool => equivalent($e, $element));
    }

    public function first(Closure $where = null): mixed
    {
        foreach ($this as $i => $e) {
            if ($where === null) {
                return $e;
            } elseif ($where($e, $i)) {
                return $e;
            }
        }
        return null;
    }

    public function min(): mixed
    {
        $min = null;
        foreach ($this as $e) {
            if ($min === null || (($e instanceof Number) ? ($e->compare($min) === ComparisonResult::orderedAscending) : ($e < $min))) {
                $min = $e;
            }
        }
        return $min;
    }

    public function max(): mixed
    {
        $max = null;
        foreach ($this as $e) {
            if ($max === null || (($e instanceof Number) ? ($e->compare($max) === ComparisonResult::orderedDescending) : ($e > $max))) {
                $max = $e;
            }
        }
        return $max;
    }

    public function reduce(mixed $initialResult, Closure $updateAccumulatingResult): mixed
    {
        foreach ($this as $i => $e) {
            $updateAccumulatingResult($initialResult, $e, $i);
        }
        return $initialResult;
    }

    public function sum(): int|float
    {
        return $this->reduce(0, function (int|float &$r, string|int|float|Number $e): int|float {
            $r += pn($e);
            return $r;
        });
    }

    public function map(Closure $transform): self
    {
        $instance = new self();
        foreach ($this as $i => $e) {
            $instance->append($transform($e, $i));
        }
        return $instance;
    }

    public function compactMap(Closure $transform): self
    {
        $instance = new self();
        foreach ($this as $i => $e) {
            $r = $transform($e, $i);
            if ($r !== null) {
                $instance->append($r);
            }
        }
        return $instance;
    }

    public function flatMap(Closure $transform): self
    {
        $instance = new self();
        foreach ($this as $i => $e) {
            $instance->appendContentsOf($transform($e, $i));
        }
        return $instance;
    }

    public function elementsEqual(Sequence $sequence, ?Closure $areEquivalent = null): bool
    {
        $areEquivalent ??= fn (mixed $e1, mixed $e2): bool => equivalent($e1, $e2);
        foreach ($this as $i => $e) {
            if (!$areEquivalent($e, $sequence->first(fn (mixed $v, string|int $k): bool => $k === $i))) {
                return false;
            }
        }
        return true;
    }

    public function isEqual(mixed $other): bool
    {
        if ($other instanceof self) {
            return $this->elementsEqual($other);
        }
        return false;
    }

    public function toArray(): array
    {
        return iterator_to_array($this);
    }

    /** @noinspection PhpMixedReturnTypeCanBeReducedInspection */
    public function jsonSerialize(): mixed
    {
        return $this->toArray();
    }
}
