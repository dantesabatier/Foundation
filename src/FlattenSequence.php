<?php

namespace Sabatier\Foundation;

use Closure;
use Generator;
use IteratorAggregate;
use Override;
use Sabatier\Foundation\Predicates\Predicate;
use Traversable;

/**
 * A sequence consisting of all the elements contained in each segment contained in some Base sequence.
 *
 * The elements of this view are a concatenation of the elements of each sequence in the base.
 * The joined method is always lazy but does not implicitly confer laziness on algorithms applied to its result.
 * @template Element
 * @implements Sequence<int, Element>
 * @implements IteratorAggregate<int, Element>
 */
final class FlattenSequence extends ObjectClass implements Sequence, IteratorAggregate
{
    use SequenceAlgorithms {
        reduce as private sequenceReduce;
    }

    public string $description {
        get => sprintf("<%s %s <%s>>", $this->class, $this->base::class, human_readable_value($this->base));
    }
    public int $count {
        get => $this->count();
    }
    public bool $isEmpty {
        get => $this->count === 0;
    }
    /** @var Element|null $first */
    public mixed $first {
        get => $this->first();
    }
    /** @var Element[] */
    public array $array {
        get => iterator_to_array($this);
    }

    /**
     * Creates a view into the given collection that allows access to elements within the specified range.
     * @param Sequence $base The collection to create a view into.
     */
    public function __construct(public readonly Sequence $base)
    {
    }

    /**
     * Returns the result of combining the elements of the sequence using the given closure.
     * Use the {@see reduce()} method to produce a single value from the elements of an entire sequence.
     * For example, you can use this method on an array of integers to filter adjacent equal entries or count frequencies.
     * @template Result
     * @param Result $initialResult The value to use as the initial accumulating value.
     * @param Closure(Result, mixed, int<0, max>=): Result $updateAccumulatingResult A closure that updates the accumulating value with an element of the sequence.
     * @return Result The final accumulated value. If the sequence has no elements, the result is $initialResult.
     */
    #[Override]
    public function reduce(mixed $initialResult, Closure $updateAccumulatingResult)
    {
        return $this->sequenceReduce($initialResult, $updateAccumulatingResult);
    }

    /**
     * @template Result
     * Returns a Collection containing the results of mapping the given closure over the collection's elements.
     * @param Closure(mixed, int<0, max>=): Result $transform
     * @return Sequence<int, Result>
     */
    #[Override]
    public function map(Closure $transform): Sequence
    {
        return new ($this->base::class)($this)->map($transform);
    }

    /**
     * @template Result
     * Returns a Collection containing the non-null results of calling the given transformation with each element of this collection.
     * @param Closure(mixed, int<0, max>=): Result $transform
     * @return Sequence<int, Result>
     */
    #[Override]
    public function compactMap(Closure $transform): Sequence
    {
        return new ($this->base::class)($this)->compactMap($transform);
    }

    /**
     * @template Result
     * Returns a Collection containing the concatenated results of calling the given transformation with each element of this collection.
     * @param Closure(mixed, int<0, max>=): iterable<Result> $transform
     * @return Sequence<int, Result>
     */
    #[Override]
    public function flatMap(Closure $transform): Sequence
    {
        return new ($this->base::class)($this)->flatMap($transform);
    }

    /**
     * Returns a Sequence containing, in order, the elements of the sequence that satisfy the given predicate.
     *
     * Complexity: O(n), where n is the length of the sequence.
     * @param Closure(Element, int<0, max>=, bool=): bool $isIncluded
     * @return Sequence<int, Element>
     */
    #[Override]
    public function filter(Closure $isIncluded): Sequence
    {
        return new ($this->base::class)($this)->filter($isIncluded);
    }

    /**
     * Evaluates a given predicate against each object in the receiving and returns a new sequence containing the objects for which the predicate returns true.
     *
     * Complexity: O(n), where n is the length of the sequence.
     * @param Predicate $predicate The predicate against which to evaluate the receiving sequence's elements.
     * @return Sequence<int, Element> A new sequence containing the objects in the receiving array for which predicate returns true.
     * Objects in the resulting array appear in the same order as they do in the receiver.
     */
    #[Override]
    public function filtered(Predicate $predicate): Sequence
    {
        return new ($this->base::class)($this)->filtered($predicate);
    }

    #[Override]
    public function getIterator(): Generator
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

    #[Override]
    public function count(): int
    {
        return iterator_count($this);
    }
}
