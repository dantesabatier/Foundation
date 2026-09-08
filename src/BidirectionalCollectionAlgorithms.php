<?php

declare(strict_types=1);

namespace Sabatier\Foundation;

use Closure;

/**
 * @psalm-require-implements BidirectionalCollection
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
            if ($where($this[$i], $i)) {
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

    /**
     * @param ArrayClass<mixed> $base
     * @param ArrayClass<mixed> $target
     * @param Closure(mixed, mixed): bool $areEquivalent
     * @param int $baseStart
     * @param int $baseLength
     * @param int $targetStart
     * @param int $targetLength
     * @return list<array{int, int}>
     */
    private function differenceMatchingOffsets(ArrayClass $base, ArrayClass $target, Closure $areEquivalent, int $baseStart, int $baseLength, int $targetStart, int $targetLength): array
    {
        if ($baseLength === 0 || $targetLength === 0) {
            return [];
        }
        if ($baseLength === 1) {
            for ($targetIndex = $targetStart; $targetIndex < $targetStart + $targetLength; $targetIndex++) {
                if ($areEquivalent($base[$baseStart], $target[$targetIndex])) {
                    return [[$baseStart, $targetIndex]];
                }
            }
            return [];
        }
        $leftBaseLength = intdiv($baseLength, 2);
        $previous = array_fill(0, $targetLength + 1, 0);
        for ($baseIndex = 0; $baseIndex < $leftBaseLength; $baseIndex++) {
            $current = array_fill(0, $targetLength + 1, 0);
            for ($targetIndex = 0; $targetIndex < $targetLength; $targetIndex++) {
                $current[$targetIndex + 1] = $areEquivalent($base[$baseStart + $baseIndex], $target[$targetStart + $targetIndex])
                    ? $previous[$targetIndex] + 1
                    : max($previous[$targetIndex + 1], $current[$targetIndex]);
            }
            $previous = $current;
        }
        $forwardLengths = $previous;
        $next = array_fill(0, $targetLength + 1, 0);
        for ($baseIndex = $baseLength - 1; $baseIndex >= $leftBaseLength; $baseIndex--) {
            $current = array_fill(0, $targetLength + 1, 0);
            for ($targetIndex = $targetLength - 1; $targetIndex >= 0; $targetIndex--) {
                $current[$targetIndex] = $areEquivalent($base[$baseStart + $baseIndex], $target[$targetStart + $targetIndex])
                    ? $next[$targetIndex + 1] + 1
                    : max($next[$targetIndex], $current[$targetIndex + 1]);
            }
            $next = $current;
        }
        $targetSplit = 0;
        $longestLength = -1;
        for ($targetIndex = 0; $targetIndex <= $targetLength; $targetIndex++) {
            $length = $forwardLengths[$targetIndex] + $next[$targetIndex];
            if ($length > $longestLength) {
                $longestLength = $length;
                $targetSplit = $targetIndex;
            }
        }
        unset($previous, $current, $forwardLengths, $next);
        return array_merge(
            $this->differenceMatchingOffsets($base, $target, $areEquivalent, $baseStart, $leftBaseLength, $targetStart, $targetSplit),
            $this->differenceMatchingOffsets($base, $target, $areEquivalent, $baseStart + $leftBaseLength, $baseLength - $leftBaseLength, $targetStart + $targetSplit, $targetLength - $targetSplit),
        );
    }

    public function difference(Collection $other, ?Closure $areEquivalent = null): CollectionDifference
    {
        $areEquivalent ??= is_equal(...);
        $base = new ArrayClass($other);
        $target = new ArrayClass($this);
        $baseCount = $base->count;
        $targetCount = $target->count;
        $prefixCount = 0;
        while ($prefixCount < $baseCount && $prefixCount < $targetCount && $areEquivalent($base[$prefixCount], $target[$prefixCount])) {
            $prefixCount += 1;
        }
        $baseEnd = $baseCount;
        $targetEnd = $targetCount;
        while ($baseEnd > $prefixCount && $targetEnd > $prefixCount && $areEquivalent($base[$baseEnd - 1], $target[$targetEnd - 1])) {
            $baseEnd -= 1;
            $targetEnd -= 1;
        }
        $baseLength = $baseEnd - $prefixCount;
        $targetLength = $targetEnd - $prefixCount;
        $matches = $this->differenceMatchingOffsets($base, $target, $areEquivalent, $prefixCount, $baseLength, $prefixCount, $targetLength);
        /** @var ArrayClass<CollectionDifferenceChange> $removals */
        $removals = new ArrayClass();
        /** @var ArrayClass<CollectionDifferenceChange> $insertions */
        $insertions = new ArrayClass();
        $baseOffset = $prefixCount;
        $targetOffset = $prefixCount;
        foreach ($matches as [$matchedBaseOffset, $matchedTargetOffset]) {
            while ($baseOffset < $matchedBaseOffset) {
                $removals->append(new CollectionDifferenceChange(CollectionDifferenceChangeType::remove, $base[$baseOffset], $baseOffset));
                $baseOffset += 1;
            }
            while ($targetOffset < $matchedTargetOffset) {
                $insertions->append(new CollectionDifferenceChange(CollectionDifferenceChangeType::insert, $target[$targetOffset], $targetOffset));
                $targetOffset += 1;
            }
            $baseOffset += 1;
            $targetOffset += 1;
        }
        while ($baseOffset < $baseEnd) {
            $removals->append(new CollectionDifferenceChange(CollectionDifferenceChangeType::remove, $base[$baseOffset], $baseOffset));
            $baseOffset += 1;
        }
        while ($targetOffset < $targetEnd) {
            $insertions->append(new CollectionDifferenceChange(CollectionDifferenceChangeType::insert, $target[$targetOffset], $targetOffset));
            $targetOffset += 1;
        }
        /** @var ArrayClass<CollectionDifferenceChange> $changes */
        $changes = $removals->reversed();
        $changes->appendContentsOf($insertions);
        return new CollectionDifference($changes);
    }
}
