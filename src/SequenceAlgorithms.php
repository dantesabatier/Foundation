<?php

namespace Sabatier\Foundation;

use Closure;

/**
 * @psalm-require-implements Sequence
 */
trait SequenceAlgorithms
{
    /** @var array<array-key, mixed> $reserved */
    protected array $reserved = [];

    public function __clone()
    {
        $this->reserved = $this->toArray();
    }

    public function __serialize(): array
    {
        return $this->reserved;
    }

    public function __unserialize(array $data): void
    {
        $this->reserved = $data;
    }

    public function count(): int
    {
        return count($this->reserved);
    }

    public function isEmpty(): bool
    {
        return $this->count() === 0;
    }

    public function compare(mixed $other): ComparisonResult
    {
        if ($other instanceof Sequence) {
            return ComparisonResult::from($this->count() <=> $other->count());
        }
        return ComparisonResult::orderedDescending;
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
        return $this->reserved;
    }
    
    public function contains(Closure $predicate): bool
    {
        /**
         * @var array-key $i
         */
        foreach ($this as $i => $e) {
            if ($predicate($e, $i)) {
                return true;
            }
        }
        return false;
    }
    
    public function containsElement(mixed $element): bool
    {
        return $this->contains(fn(mixed $e): bool => equivalent($e, $element));
    }
    
    public function elementsEqual(Sequence $sequence, ?Closure $areEquivalent = null): bool
    {
        if ($this->compare($sequence) != ComparisonResult::orderedSame) {
            return false;
        }
        $areEquivalent ??= fn(mixed $e0, mixed $e1): bool => equivalent($e0, $e1);
        foreach ($this as $i => $e) {
            if (!$areEquivalent($e, $sequence->first(fn(mixed $v, string|int $k): bool => $k === $i))) {
                return false;
            }
        }
        return true;
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
            if ($min === null || (($e instanceof Comparable) ? ($e->compare($min) === ComparisonResult::orderedAscending) : ($e < $min))) {
                $min = $e;
            }
        }
        return $min;
    }
    
    public function max(): mixed
    {
        $max = null;
        foreach ($this as $e) {
            if ($max === null || (($e instanceof Comparable) ? ($e->compare($max) === ComparisonResult::orderedDescending) : ($e > $max))) {
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
        return $this->reduce(0, fn(int|float &$r, string|int|float|Number $e): int|float => $r += pn($e));
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

    /** @noinspection PhpMixedReturnTypeCanBeReducedInspection */
    public function jsonSerialize(): mixed
    {
        return $this->toArray();
    }
}
