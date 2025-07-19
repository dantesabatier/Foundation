<?php

namespace Sabatier\Foundation;

use Closure;
use Override;
use Sabatier\Foundation\Predicates\Predicate;

/**
 * @phpstan-require-implements Sequence
 */
trait SequenceAlgorithms
{
    /** @var array<array-key, mixed> $reserved */
    protected array $reserved = [];

    public function __clone()
    {
        $this->reserved = $this->array;
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
        return $this->count;
    }

    #[Override]
    public function compare(mixed $other): ComparisonResult
    {
        if (!$other instanceof Sequence) {
            fatal_error(sprintf("Invalid argument: expecting %s, \"%s\" given", Sequence::class, typeof($other)));
        }
        return ComparisonResult::from($this->count() <=> $other->count());
    }

    #[Override]
    public function isEqual(mixed $other): bool
    {
        return $this->elementsEqual($other);
    }

    public function filter(Closure $isIncluded): self
    {
        $instance = new self();
        foreach (clone $this as $i => $e) {
            $stop = false;
            if ($isIncluded($e, $i, $stop)) {
                $instance[] = $e;
            }
            /** @psalm-suppress TypeDoesNotContainType */
            if ($stop) {
                break;
            }
        }
        return $instance;
    }

    public function filtered(Predicate $predicate): self
    {
        return $this->filter(fn(mixed $e): bool => $predicate->evaluate($e));
    }

    public function contains(Closure $predicate): bool
    {
        /** @noinspection PhpLoopCanBeConvertedToArrayAnyInspection */
        foreach (clone $this as $i => $e) {
            if ($predicate($e, $i)) {
                return true;
            }
        }
        return false;
    }

    public function containsElement(mixed $element): bool
    {
        return $this->contains(fn(mixed $e): bool => is_equal($e, $element));
    }

    public function allSatisfy(Closure $predicate): bool
    {
        return !$this->contains(fn(mixed $e, string|int $i): bool => !$predicate($e, $i));
    }

    public function elementsEqual(Sequence $sequence, ?Closure $areEquivalent = null): bool
    {
        if ($this->compare($sequence) !== ComparisonResult::orderedSame) {
            return false;
        }
        $areEquivalent ??= fn(mixed $e0, mixed $e1): bool => is_equal($e0, $e1);
        return $this->allSatisfy(fn(mixed $e, string|int $i) => $areEquivalent($e, $sequence->first(fn(mixed $v, string|int $k): bool => $k === $i)));
    }

    public function first(?Closure $where = null): mixed
    {
        foreach (clone $this as $i => $e) {
            if ($where === null) {
                return $e;
            }
            if ($where($e, $i)) {
                return $e;
            }
        }
        return null;
    }

    public function min(): mixed
    {
        $min = null;
        foreach (clone $this as $e) {
            if ($min === null || (($e instanceof Comparable) ? ($e->compare($min) === ComparisonResult::orderedAscending) : ($e < $min))) {
                $min = $e;
            }
        }
        return $min;
    }

    public function max(): mixed
    {
        $max = null;
        foreach (clone $this as $e) {
            if ($max === null || (($e instanceof Comparable) ? ($e->compare($max) === ComparisonResult::orderedDescending) : ($e > $max))) {
                $max = $e;
            }
        }
        return $max;
    }

    public function reduce(mixed $initialResult, Closure $updateAccumulatingResult): mixed
    {
        foreach (clone $this as $i => $e) {
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
        foreach (clone $this as $i => $e) {
            $instance->append($transform($e, $i));
        }
        return $instance;
    }

    public function compactMap(Closure $transform): self
    {
        $instance = new self();
        foreach (clone $this as $i => $e) {
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
        foreach (clone $this as $i => $e) {
            $instance->appendContentsOf($transform($e, $i));
        }
        return $instance;
    }

    /** @noinspection PhpMixedReturnTypeCanBeReducedInspection */
    #[Override]
    public function jsonSerialize(): mixed
    {
        return $this->array;
    }
}
