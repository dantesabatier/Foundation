<?php

namespace Sabatier\Foundation;

use Closure;
use Generator;
use IteratorAggregate;
use Traversable;

/**
 * Class FlattenSequence
 * A sequence consisting of all the elements contained in each segment contained in some Base sequence.
 * The elements of this view are a concatenation of the elements of each sequence in the base.
 * The joined method is always lazy, but does not implicitly confer laziness on algorithms applied to its result.
 * @package Sabatier\Foundation
 * @template-covariant Base of Collection
 * @implements Sequence<int, mixed>
 * @implements IteratorAggregate<int, mixed>
 */
class FlattenSequence extends ObjectClass implements Sequence, IteratorAggregate
{
    use SequenceAlgorithms {
        reduce as protected sequenceReduce;
    }

    /** @var Base $base The underlying collection of the FlattenSequence. */
    public readonly mixed $base;

    /**
     * Creates a view into the given collection that allows access to elements within the specified range.
     * @param Base $base The collection to create a view into.
     */
    public function __construct(mixed $base)
    {
        $this->base = $base;
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
     * @return ArrayClass<Result>
     */
    public function map(Closure $transform): ArrayClass
    {
        return (new ArrayClass($this))->map($transform);
    }

    /**
     * @template Result
     * Returns a Collection containing the non-nil results of calling the given transformation with each element of this collection.
     * @param Closure(mixed, int=): Result $transform
     * @return ArrayClass<Result>
     */
    public function compactMap(Closure $transform): ArrayClass
    {
        return (new ArrayClass($this))->compactMap($transform);
    }

    /**
     * @template Result
     * Returns a Collection containing the concatenated results of calling the given transformation with each element of this collection.
     * @param Closure(mixed, int=): iterable<Result> $transform
     * @return ArrayClass<Result>
     */
    public function flatMap(Closure $transform): ArrayClass
    {
        return (new ArrayClass($this))->flatMap($transform);
    }

    public function getIterator(): Traversable
    {
        return (function (): Generator {
            $recursive = function (iterable $iterable) use (&$recursive): Traversable {
                foreach ($iterable as $item) {
                    if (is_array($item) || ($item instanceof Traversable && !$item instanceof Dictionary)) {
                        foreach ($recursive($item) as $v) {
                            yield $v;
                        }
                    } else {
                        yield $item;
                    }
                }
            };
            foreach ($recursive($this->base) as $element) {
                yield $element;
            }
        })();
    }

    public function description(): string
    {
        return sprintf("<%s %s <%s>>", static::class, $this->base::class, human_readable_value($this->base));
    }
}
