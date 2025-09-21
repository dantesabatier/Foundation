<?php

namespace Sabatier\Foundation;

use Closure;
use Generator;
use IteratorAggregate;
use Override;
use Sabatier\Foundation\Predicates\Predicate;
use Traversable;

/**
 * A view into a subsequence of elements of another collection.
 *
 * A slice stores a base collection and the start and end indices of the view.
 * It does not copy the elements from the collection into separate storage.
 * @template Element
 * @implements Collection<int, Element>
 * @implements IteratorAggregate<int, Element>
 */
class Slice extends ObjectClass implements Collection, IteratorAggregate
{
    use CollectionAlgorithms {
        reduce as private sequenceReduce;
    }

    public int $count {
        get => $this->bounds->count;
    }
    public bool $isEmpty {
        get => $this->bounds->isEmpty;
    }
    /** @var Element|null $first */
    public mixed $first {
        get => $this->first();
    }
    public int $startIndex {
        get => $this->bounds->lowerBound;
    }
    public int $endIndex {
        get => $this->bounds->upperBound;
    }
    public Range $indices {
        get => $this->bounds;
    }
    /** @var Element[] */
    private(set) array $array {
        get => $this->array ??= array_slice($this->base->array, $this->startIndex, $this->endIndex);
    }
    public string $description {
        get => sprintf("<%s %s [%s...<%s]>", typeof($this->base), human_readable_value($this->base), $this->startIndex, $this->endIndex);
    }

    /**
     * Creates a view into the given collection that allows access to elements within the specified range.
     * @param Collection<int, Element> $base The underlying collection of the Slice.
     * @param Range $bounds The range of indices to allow access to in the new slice.
     */
    public function __construct(public readonly Collection $base, public readonly Range $bounds)
    {
    }

    #[Override]
    public function count(): int
    {
        return $this->count;
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
     * @return Collection<int, Result>
     * @psalm-suppress LessSpecificReturnStatement, MoreSpecificReturnType
     */
    #[Override]
    public function map(Closure $transform): Collection
    {
        return new ($this->base::class)($this)->map($transform);
    }

    /**
     * @template Result
     * Returns a Collection containing the non-null results of calling the given transformation with each element of this collection.
     * @param Closure(mixed, int<0, max>=): Result $transform
     * @return Collection<int, Result>
     * @psalm-suppress LessSpecificReturnStatement, MoreSpecificReturnType
     */
    #[Override]
    public function compactMap(Closure $transform): Collection
    {
        return new ($this->base::class)($this)->compactMap($transform);
    }

    /**
     * @template Result
     * Returns a Collection containing the concatenated results of calling the given transformation with each element of this collection.
     * @param Closure(mixed, int<0, max>=): iterable<Result> $transform
     * @return Collection<int, Result>
     * @psalm-suppress LessSpecificReturnStatement, MoreSpecificReturnType
     */
    #[Override]
    public function flatMap(Closure $transform): Collection
    {
        return new ($this->base::class)($this)->flatMap($transform);
    }

    /**
     * Returns a Collection containing, in order, the elements of the collection that satisfy the given predicate.
     * @param Closure(Element, int=, bool=): bool $isIncluded
     * @return ArrayClass<Element>
     */
    #[Override]
    public function filter(Closure $isIncluded): ArrayClass
    {
        $instance = new ArrayClass();
        foreach ($this as $i => $e) {
            $stop = false;
            if ($isIncluded($e, $i, $stop)) {
                $instance[] = $e;
            }
            /** @noinspection PhpConditionAlreadyCheckedInspection */
            if ($stop) {
                break;
            }
        }
        return $instance;
    }

    /**
     * Evaluates a given predicate against each object in the receiving and returns a new collection containing the objects for which the predicate returns true.
     * @param Predicate $predicate The predicate against which to evaluate the receiving collection's elements.
     * @return ArrayClass<Element> A new collection containing the objects in the receiving array for which predicate returns true.
     * Objects in the resulting array appear in the same order as they do in the receiver.
     */
    #[Override]
    public function filtered(Predicate $predicate): ArrayClass
    {
        return $this->filter(fn(mixed $e): bool => $predicate->evaluate($e));
    }

    #[Override]
    public function sort(?Closure $by = null): ArrayClass
    {
        invalid_mutation();
    }

    #[Override]
    public function sorted(iterable $descriptors): ArrayClass
    {
        unsupported($this, __FUNCTION__);
    }

    /**
     * Returns the elements of this sequence of sequences, concatenated.
     * @return FlattenSequence<Element> A flattened view of the elements of this sequence of sequences.
     * @psalm-suppress LessSpecificReturnStatement, MoreSpecificReturnType
     */
    #[Override]
    public function joined(): FlattenSequence
    {
        /** @psalm-suppress InvalidArgument */
        return new FlattenSequence($this->base);
    }

    #[Override]
    public function valueForKey(string $key): Collection
    {
        return $this->map(fn(KeyValueCoding $e): mixed => $e->valueForKey($key));
    }

    /**
     * @return Traversable<int, Element>
     */
    #[Override]
    public function getIterator(): Traversable
    {
        return (function (): Generator {
            foreach ($this->bounds as $e) {
                yield $e => $this->base[$e];
            }
        })();
    }

    #[Override]
    public function jsonSerialize(): array
    {
        return $this->array;
    }
}
