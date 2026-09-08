<?php

declare(strict_types=1);

namespace Sabatier\Foundation;

/**
 * @psalm-require-implements RangeReplaceableCollection
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
        assert($subrange->lowerBound >= $this->startIndex && $subrange->upperBound <= $this->endIndex, "Invalid argument: subrange bounds must be valid collection indices");
        // The replacement may be a view into this collection, so preserve its elements before mutation invalidates the view.
        $replacement = $newElements->map(fn(mixed $element): mixed => $element);
        $this->removeSubrange($subrange);
        $insertionIndex = $subrange->lowerBound;
        $replacement->forEach(function (mixed $element) use (&$insertionIndex): void {
            $this->insertAt($element, $insertionIndex);
            $this->formIndexAfter($insertionIndex);
        });
    }

    public function removeSubrange(Range $subrange): void
    {
        $this->removeAll(fn(mixed $e, int $i): bool => $subrange->contains($i));
    }
}
