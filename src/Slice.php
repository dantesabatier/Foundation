<?php

declare(strict_types=1);

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
final class Slice extends ObjectClass implements Collection, IteratorAggregate
{
    use CollectionAlgorithms {
        reduce as private sequenceReduce;
    }

    /** @var int<0, max> */
    #[Override]
    public int $count {
        get => $this->bounds->count;
    }
    #[Override]
    public bool $isEmpty {
        get => $this->bounds->isEmpty;
    }
    /** @var Element|null $first */
    #[Override]
    public mixed $first {
        get => $this->first();
    }
    #[Override]
    public int $startIndex {
        get => $this->bounds->lowerBound;
    }
    #[Override]
    public int $endIndex {
        get => $this->bounds->upperBound;
    }
    #[Override]
    public Range $indices {
        get => $this->bounds;
    }
    /** @var list<Element> */
    #[Override]
    public array $array {
        get => array_slice($this->base->array, $this->startIndex - $this->base->startIndex, $this->count);
    }
    #[Override]
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
        $this->validateBounds();
    }

    /** @return array{base: Collection<int, Element>, bounds: Range} */
    public function __serialize(): array
    {
        return ["base" => $this->base, "bounds" => $this->bounds];
    }

    /** @param array{base: Collection<int, Element>, bounds: Range} $data */
    public function __unserialize(array $data): void
    {
        $this->base = $data["base"];
        $this->bounds = $data["bounds"];
        $this->validateBounds();
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
     * @param Closure(Result, mixed, int=): Result $updateAccumulatingResult A closure that updates the accumulating value with an element of the sequence.
     * @return Result The final accumulated value. If the sequence has no elements, the result is $initialResult.
     */
    #[Override]
    public function reduce(mixed $initialResult, Closure $updateAccumulatingResult)
    {
        return $this->sequenceReduce($initialResult, $updateAccumulatingResult);
    }

    /**
     * @template Result
     * Returns an ArrayClass containing the results of mapping the given closure over the slice's elements.
     * @param Closure(mixed, int=): Result $transform
     * @return ArrayClass<Result>
     */
    #[Override]
    public function map(Closure $transform): ArrayClass
    {
        return new ArrayClass($this)->map($transform);
    }

    /**
     * @template Result
     * Returns an ArrayClass containing the non-null results of transforming the slice's elements.
     *
     * @param Closure(mixed, int=): ?Result $transform
     * @return ArrayClass<Result>
     */
    #[Override]
    public function compactMap(Closure $transform): ArrayClass
    {
        return new ArrayClass($this)->compactMap($transform);
    }

    /**
     * @template Result
     * Returns an ArrayClass containing the concatenated results of transforming the slice's elements.
     * @param Closure(mixed, int=): iterable<Result> $transform
     * @return ArrayClass<Result>
     */
    #[Override]
    public function flatMap(Closure $transform): ArrayClass
    {
        return new ArrayClass($this)->flatMap($transform);
    }

    /**
     * Calls the given closure on each element in the sequence in the same order as a for-in loop.
     *
     * The two loops in the following example produce the same output:
     *
     * <code>
     * $numberWords = ["one", "two", "three"]
     * foreach ($numberWords as $word) {
     *     print "$word\n";
     * }
     * // Prints "one"
     * // Prints "two"
     * // Prints "three"
     * $numberWords->forEach(fn(string $word): void => print "$word\n");
     * // Same as above
     * </code>
     *
     * You cannot use a break or continue statement to exit the current call of the body closure or skip later calls.
     * @param Closure(Element, int=): void $body A closure that takes an element of the sequence as a parameter.
     */
    #[Override]
    public function forEach(Closure $body): void
    {
        foreach (clone $this as $i => $e) {
            $body($e, $i);
        }
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
                $instance->append($e);
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
     *
     * @return FlattenSequence<Element> A flattened view of the elements of this sequence of sequences.
     */
    #[Override]
    public function joined(): FlattenSequence
    {
        return new FlattenSequence($this);
    }

    /**
     * Returns the first index in which an element of the collection satisfies the given predicate.
     *
     * Complexity O(n), where n is the length of the collection.
     * @param Closure(Element): bool $where A closure that takes an element as its argument and returns a Boolean value that indicates whether the passed element represents a match.
     * @return int|null The index of the first element for which $where returns true.
     * If no elements in the collection satisfy the given $where returns null.
     */
    #[Override]
    public function firstIndex(Closure $where): int|null
    {
        foreach (clone $this as $idx => $item) {
            if ($where($item)) {
                return $idx;
            }
        }
        return null;
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
            foreach ($this->array as $offset => $element) {
                yield $this->startIndex + $offset => $element;
            }
        })();
    }

    #[Override]
    public function jsonSerialize(): array
    {
        return $this->array;
    }

    private function validateBounds(): void
    {
        if ($this->bounds->lowerBound < $this->base->startIndex || $this->bounds->upperBound > $this->base->endIndex) {
            fatal_error(sprintf("Invalid argument: slice bounds %s are outside collection indices %s", $this->bounds->description, $this->base->indices->description));
        }
    }
}
