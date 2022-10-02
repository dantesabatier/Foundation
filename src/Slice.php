<?php

namespace Sabatier\Foundation;

use Countable;
use Generator;
use IteratorAggregate;
use JetBrains\PhpStorm\Pure;
use Traversable;

/**
 * Class Slice
 * A view into a subsequence of elements of another collection.
 * A slice stores a base collection and the start and end indices of the view.
 * It does not copy the elements from the collection into separate storage.
 * @package Sabatier\Foundation
 * @template-covariant Base of Collection
 * @implements IteratorAggregate<int, mixed>
 */
class Slice extends ObjectClass implements ExpressibleByArrayLiteral, IteratorAggregate, Countable
{
    /** @var Base $base */
    public readonly mixed $base;
    public readonly int $startIndex;
    public readonly int $endIndex;

    /**
     * Creates a view into the given collection that allows access to elements within the specified range.
     * @param Base $base The underlying collection of the slice.
     * @param Range $bounds The range of indices to allow access to in the new slice.
     */
    #[Pure]
    public function __construct(mixed $base, Range $bounds)
    {
        $this->base = $base;
        $this->startIndex = $bounds->lowerBound;
        $this->endIndex = $bounds->upperBound;
    }

    public function count(): int
    {
        return $this->endIndex - $this->startIndex;
    }

    #[Pure]
    public function isEmpty(): bool
    {
        return $this->endIndex == $this->startIndex;
    }

    public function getIterator(): Traversable
    {
        return (function (): Generator {
            for ($index = $this->startIndex; $index < $this->endIndex; $index++) {
                yield $index => $this->base[$index];
            }
        })();
    }

    public function description(): string
    {
        return sprintf("<%s %s [%s...<%s]>", typeof($this->base), human_readable_value($this->base), $this->startIndex, $this->endIndex);
    }

    public function toArray(): array
    {
        return array_slice($this->base->toArray(), $this->startIndex, $this->count());
    }

    public function jsonSerialize(): mixed
    {
        return $this->toArray();
    }
}
