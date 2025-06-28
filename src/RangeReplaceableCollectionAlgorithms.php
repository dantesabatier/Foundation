<?php

namespace Sabatier\Foundation;

/**
 * @phpstan-require-implements RangeReplaceableCollection
 */
trait RangeReplaceableCollectionAlgorithms
{
    use MutableCollectionAlgorithms;

    public static function repeating(mixed $value, int $count): self
    {
        assert($count >= 0, "Invalid argument: count must be zero or greater");
        return new self(array_fill(0, $count, $value));
    }
    
    public function replaceSubrange(Range $subrange, Collection $newElements): void
    {
        assert($subrange->count() <= $this->count() && $subrange->count() === $newElements->count(), "Invalid argument: the range's upper bound must be less or equal to the count of the receiver and, range and collection must have the same number of elements");
        foreach ($subrange as $idx => $bound) {
            $this->removeAt($bound);
            $this->insertAt($newElements[$idx], $bound);
        }
    }

    public function removeSubrange(Range $subrange): void
    {
        $this->removeAll(fn(mixed $e, int $i): bool => $subrange->contains($i));
    }
}
