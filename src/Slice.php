<?php

namespace Sabatier\Foundation;

use Closure;
use Generator;
use IteratorAggregate;
use Traversable;

/**
 * A view into a subsequence of elements of another collection.
 *
 * A slice stores a base collection and the start and end indices of the view.
 * It does not copy the elements from the collection into separate storage.
 * @template Element
 * @implements Sequence<int, Element>
 * @implements IteratorAggregate<int, Element>
 */
class Slice extends ObjectClass implements Sequence, IteratorAggregate
{
    use SequenceAlgorithms {
        reduce as private sequenceReduce;
    }

    public readonly int $startIndex;
    public readonly int $endIndex;

    /**
     * Creates a view into the given collection that allows access to elements within the specified range.
     * @param Collection<int, Element> $base The underlying collection of the slice.
     * @param Range $bounds The range of indices to allow access to in the new slice.
     */
    public function __construct(public readonly Collection $base, Range $bounds)
    {
        $this->startIndex = $bounds->lowerBound;
        $this->endIndex = $bounds->upperBound;
    }

    /**
     * Returns the result of combining the elements of the sequence using the given closure.
     * Use the {@see reduce()} method to produce a single value from the elements of an entire sequence.
     * For example, you can use this method on an array of integers to filter adjacent equal entries or count frequencies.
     * @template Result
     * @param Result $initialResult The value to use as the initial accumulating value.
     * @param Closure(Result, mixed, int=): Result $updateAccumulatingResult A closure that updates the accumulating value with an element of the sequence.
     * @return Result The final accumulated value. If the sequence has no elements, the result is initialResult.
     */
    public function reduce(mixed $initialResult, Closure $updateAccumulatingResult)
    {
        return $this->sequenceReduce($initialResult, $updateAccumulatingResult);
    }

    /**
     * @template Result
     * Returns a Collection containing the results of mapping the given closure over the collection's elements.
     * @param Closure(mixed, int=): Result $transform
     * @return Sequence<int, Result>
     */
    public function map(Closure $transform): Sequence
    {
        $baseClass = $this->base::class;
        return (new $baseClass($this))->map($transform);
    }

    /**
     * @template Result
     * Returns a Collection containing the non-nil results of calling the given transformation with each element of this collection.
     * @param Closure(mixed, int=): Result $transform
     * @return Sequence<int, Result>
     */
    public function compactMap(Closure $transform): Sequence
    {
        $baseClass = $this->base::class;
        return (new $baseClass($this))->compactMap($transform);
    }

    /**
     * @template Result
     * Returns a Collection containing the concatenated results of calling the given transformation with each element of this collection.
     * @param Closure(mixed, int=): iterable<Result> $transform
     * @return Sequence<int, Result>
     */
    public function flatMap(Closure $transform): Sequence
    {
        $baseClass = $this->base::class;
        return (new $baseClass($this))->flatMap($transform);
    }

    public function count(): int
    {
        return $this->endIndex - $this->startIndex;
    }

    public function isEmpty(): bool
    {
        return $this->endIndex === $this->startIndex;
    }

    /**
     * @return Traversable<int, Element>
     */
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

    /**
     * @return Element[]
     */
    public function toArray(): array
    {
        return array_slice($this->base->toArray(), $this->startIndex, $this->count());
    }

    public function jsonSerialize(): array
    {
        return $this->toArray();
    }
}
