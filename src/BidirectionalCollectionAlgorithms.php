<?php

namespace Sabatier\Foundation;

use Closure;

/**
 * @phpstan-require-implements BidirectionalCollection
 */
trait BidirectionalCollectionAlgorithms
{
    use CollectionAlgorithms;

    public function indexBefore(int $i): int
    {
        return $i - 1;
    }

    public function formIndexBefore(int &$i): void
    {
        $i -= 1;
    }

    /** @noinspection PhpMixedReturnTypeCanBeReducedInspection */
    public function lastIndex(Closure $where): mixed
    {
        $start = $this->startIndex;
        $i = $this->endIndex;
        while ($i !== $start) {
            $this->formIndexBefore($i);
            if ($where($this[$i])) {
                return $i;
            }
        }
        return null;
    }

    public function last(?Closure $where = null): mixed
    {
        if ($this->isEmpty) {
            return null;
        }
        if ($where === null) {
            return $this[$this->indexBefore($this->endIndex)];
        }
        $i = $this->lastIndex($where);
        if ($i !== null) {
            return $this[$i];
        }
        return null;
    }

    public function randomElement(RandomNumberGenerator $generator = new SystemRandomNumberGenerator()): mixed
    {
        if ($this->isEmpty) {
            return null;
        }
        $i = $generator->next($this->indexBefore($this->endIndex));
        if ($this instanceof Dictionary) {
            return $this[$this->keys[$i]];
        }
        return $this[$i];
    }

    public function reverse(): self
    {
        $this->reserved = array_reverse($this->reserved);
        return $this;
    }

    public function reversed(): self
    {
        $instance = clone $this;
        $instance->reverse();
        return $instance;
    }

    public function difference(Collection $other, ?Closure $areEquivalent = null): CollectionDifference
    {
        $areEquivalent ??= is_equal(...);
        /** @var ArrayClass<CollectionDifferenceChange> $removals */
        $removals = new ArrayClass();
        /** @var ArrayClass<CollectionDifferenceChange> $insertions */
        $insertions = new ArrayClass();
        foreach ($other as $index => $item) {
            if (!$this->contains(fn(mixed $change): bool => $areEquivalent($change, $item))) {
                $removals->append(new CollectionDifferenceChange(CollectionDifferenceChangeType::remove, $item, $index));
            }
        }
        foreach ($this as $index => $item) {
            if (!$other->contains(fn(mixed $change): bool => $areEquivalent($change, $item))) {
                $insertions->append(new CollectionDifferenceChange(CollectionDifferenceChangeType::insert, $item, $index));
            }
        }
        $changes = $removals->sorted([new SortDescriptor("offset", false)]);
        $changes->appendContentsOf($insertions->sorted([new SortDescriptor("offset", true)]));
        return new CollectionDifference($changes);
    }
}
