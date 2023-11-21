<?php

namespace Sabatier\Foundation;

use Closure;
use Generator;
use IteratorAggregate;
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

    public readonly int $startIndex;
    public readonly int $endIndex;
    public readonly Range $indices;

    /**
     * Creates a view into the given collection that allows access to elements within the specified range.
     * @param Collection<int, Element> $base The underlying collection of the slice.
     * @param Range $bounds The range of indices to allow access to in the new slice.
     */
    public function __construct(public readonly Collection $base, Range $bounds)
    {
        $this->indices = $bounds;
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
     * @return Collection<int, Result>
     */
    public function map(Closure $transform): Collection
    {
        return (new ($this->base::class)($this))->map($transform);
    }

    /**
     * @template Result
     * Returns a Collection containing the non-nil results of calling the given transformation with each element of this collection.
     * @param Closure(mixed, int=): Result $transform
     * @return Collection<int, Result>
     */
    public function compactMap(Closure $transform): Collection
    {
        return (new ($this->base::class)($this))->compactMap($transform);
    }

    /**
     * @template Result
     * Returns a Collection containing the concatenated results of calling the given transformation with each element of this collection.
     * @param Closure(mixed, int=): iterable<Result> $transform
     * @return Collection<int, Result>
     */
    public function flatMap(Closure $transform): Collection
    {
        return (new ($this->base::class)($this))->flatMap($transform);
    }

    public function startIndex(): int
    {
        return $this->startIndex;
    }

    public function endIndex(): int
    {
        return $this->endIndex;
    }

    public function indices(): Range
    {
        return $this->indices;
    }

    /**
     * Returns a Collection containing, in order, the elements of the collection that satisfy the given predicate.
     * @param Closure(Element, int=, bool=): bool $isIncluded
     * @return ArrayClass<Element>
     */
    public function filter(Closure $isIncluded): ArrayClass
    {
        $instance = new ArrayClass();
        foreach ($this as $i => $e) {
            $stop = false;
            if ($isIncluded($e, $i, $stop)) {
                $instance[] = $e;
            }
            /** @psalm-suppress TypeDoesNotContainType */
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
    public function filtered(Predicate $predicate): ArrayClass
    {
        return $this->filter(fn(mixed $e): bool => $predicate->evaluate($e));
    }

    public function sort(?Closure $by = null): ArrayClass
    {
        invalid_mutation();
    }

    public function sorted(iterable $descriptors): ArrayClass
    {
        invalid_mutation();
    }

    public function joined(): FlattenSequence
    {
        unsupported($this, __FUNCTION__);
    }

    public function valueForKey(string $key): Collection
    {
        return $this->map(function (KeyValueCoding $e) use ($key): mixed {
            assert($e instanceof KeyValueCoding, sprintf("Invalid argument: expecting %s, \"%s\" given", KeyValueCoding::class, typeof($e)));
            return $e->valueForKey($key);
        });
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
