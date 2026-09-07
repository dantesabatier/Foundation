<?php

declare(strict_types=1);

namespace Sabatier\Foundation;

use ArrayAccess;
use Closure;
use Iterator;
use Override;
use Sabatier\Foundation\Predicates\Predicate;

/**
 * A collection of insertions and removals that describe the difference between two ordered collection states.
 * @implements MutableCollection<int, CollectionDifferenceChange>
 * @implements ArrayAccess<int, CollectionDifferenceChange>
 * @implements Iterator<int, CollectionDifferenceChange>
 */
final class CollectionDifference extends ObjectClass implements MutableCollection, ArrayAccess, Iterator
{
    use MutableCollectionAlgorithms {
        filtered as private sequenceFiltered;
        allSatisfy as private sequenceAllSatisfy;
        contains as private sequenceContains;
        containsElement as private sequenceContainsElement;
        first as private sequenceFirst;
        min as private sequenceMin;
        max as private sequenceMax;
        reduce as private sequenceReduce;
        joined as private sequenceJoined;
        firstIndex as private collectionFirstIndex;
        indexOf as private collectionIndexOf;
        sort as private collectionSort;
        sorted as private collectionSorted;
        last as private bidirectionalCollectionLast;
        lastIndex as private bidirectionalCollectionLastIndex;
        randomElement as private bidirectionalCollectionRandomElement;
        reverse as private bidirectionalCollectionReverse;
        reversed as private bidirectionalCollectionReversed;
        insertAt as private mutableCollectionInsertAt;
        remove as private mutableCollectionRemove;
        removeAt as private mutableCollectionRemoveAt;
        removeFirst as private mutableCollectionRemoveFirst;
        removeLast as private mutableCollectionRemoveLast;
        removeAll as private mutableCollectionRemoveAll;
        drop as private mutableCollectionDrop;
        dropFirst as private mutableCollectionDropFirst;
        dropLast as private mutableCollectionDropLast;
        popFirst as private mutableCollectionPopFirst;
        popLast as private mutableCollectionPopLast;
    }

    use IteratorAlgorithms {
        current as private iteratorCurrent;
    }

    #[Override]
    public string $description {
        get => "[{$this->join(", ")}]";
    }
    /** @var int<0, max> */
    #[Override]
    public int $count {
        get => count($this->reserved);
    }
    #[Override]
    public bool $isEmpty {
        get => $this->count === 0;
    }
    /** @var CollectionDifferenceChange|null $first */
    #[Override]
    public mixed $first {
        get => $this->first();
    }
    /** @var CollectionDifferenceChange|null $last */
    #[Override]
    public mixed $last {
        get => $this->last();
    }
    #[Override]
    public int $startIndex {
        get => 0;
    }
    #[Override]
    public int $endIndex {
        get => $this->count;
    }
    #[Override]
    public Range $indices {
        get => new Range($this->startIndex, $this->endIndex);
    }
    /** @var list<CollectionDifferenceChange> */
    #[Override]
    public array $array {
        get => $this->reserved;
    }
    /** @var ArrayClass<CollectionDifferenceChange> The insertions contained by this difference, from the lowest offset to the highest. */
    public ArrayClass $insertions {
        get => $this->filter(fn(CollectionDifferenceChange $change) => $change->type === CollectionDifferenceChangeType::insert)->sorted([new SortDescriptor("offset", true)]);
    }
    /** @var ArrayClass<CollectionDifferenceChange> The removals contained by this difference, from the lowest offset to the highest. */
    public ArrayClass $removals {
        get => $this->filter(fn(CollectionDifferenceChange $change) => $change->type === CollectionDifferenceChangeType::remove)->sorted([new SortDescriptor("offset", true)]);
    }

    /**
     * @param iterable<CollectionDifferenceChange> $elements
     */
    public function __construct(iterable $elements = [])
    {
        if ($elements instanceof ExpressibleByArrayLiteral) {
            $this->reserved = $elements->array;
        } elseif (is_array($elements)) {
            $this->reserved = is_sequential($elements) ? $elements : array_values($elements);
        } else {
            foreach ($elements as $element) {
                $this->append($element);
            }
        }
    }

    /**
     * Returns a new collection difference with associations between individual elements that have been removed and inserted only once.
     * @return CollectionDifference
     */
    public function inferringMoves(): CollectionDifference
    {
        $removals = $this->removals->map(fn(CollectionDifferenceChange $change) => clone $change);
        $insertions = $this->insertions->map(fn(CollectionDifferenceChange $change) => clone $change);
        $moves = $removals->compactMap(function (CollectionDifferenceChange $removal) use ($removals, $insertions): ?CollectionDifferenceChange {
            if ($removals->filter(fn(CollectionDifferenceChange $candidate): bool => is_equal($candidate->element, $removal->element))->count !== 1) {
                return null;
            }
            $matchingInsertions = $insertions->filter(fn(CollectionDifferenceChange $candidate): bool => is_equal($candidate->element, $removal->element));
            if ($matchingInsertions->count !== 1) {
                return null;
            }
            /** @var CollectionDifferenceChange $insertion */
            $insertion = $matchingInsertions->first;
            return new CollectionDifferenceChange(CollectionDifferenceChangeType::move, $removal->element, $removal->offset, $insertion->offset);
        });
        /** @var ArrayClass<CollectionDifferenceChange> $newChanges */
        $newChanges = $removals->filter(fn(CollectionDifferenceChange $removal): bool => !$moves->contains(fn(CollectionDifferenceChange $move): bool => $move->offset === $removal->offset))->reversed();
        $newChanges->appendContentsOf($moves);
        $newChanges->appendContentsOf($insertions->filter(fn(CollectionDifferenceChange $insertion): bool => !$moves->contains(fn(CollectionDifferenceChange $move): bool => $move->targetOffset === $insertion->offset)));
        return new CollectionDifference($newChanges);
    }

    /**
     * @return CollectionDifference
     */
    public function inverse(): CollectionDifference
    {
        return new CollectionDifference($this->map(fn(CollectionDifferenceChange $change): CollectionDifferenceChange => match ($change->type) {
            CollectionDifferenceChangeType::insert => new CollectionDifferenceChange(CollectionDifferenceChangeType::remove, $change->element, $change->offset, $change->targetOffset),
            CollectionDifferenceChangeType::remove => new CollectionDifferenceChange(CollectionDifferenceChangeType::insert, $change->element, $change->offset, $change->targetOffset),
            CollectionDifferenceChangeType::move => new CollectionDifferenceChange(CollectionDifferenceChangeType::move, $change->element, $change->targetOffset ?? $change->offset, $change->offset),
        })->reversed());
    }

    /**
     * Returns a Boolean value indicating whether the sequence contains an element that satisfies the given predicate.
     * @param Closure(CollectionDifferenceChange, int): bool $predicate A closure that takes an element of the sequence as its argument and returns a Boolean value that indicates whether the passed element represents a match.
     * @return bool true if the sequence contains an element that satisfies predicate; otherwise, false.
     */
    #[Override]
    public function contains(Closure $predicate): bool
    {
        return $this->sequenceContains($predicate);
    }

    /**
     * Returns a bool value indicating whether the sequence contains an element that satisfies the given predicate.
     *
     * Available when CollectionDifferenceChange conforms to {@see Equatable}.
     * @param CollectionDifferenceChange $element The element to find in the sequence.
     * @return bool true if the element was found in the sequence; otherwise, false.
     */
    #[Override]
    public function containsElement(mixed $element): bool
    {
        return $this->sequenceContainsElement($element);
    }

    /**
     * Returns the minimum element in the sequence.
     * @return CollectionDifferenceChange|null The sequence's minimum element. If the sequence has no elements, it returns null.
     */
    #[Override]
    public function min()
    {
        return $this->sequenceMin();
    }

    /**
     * Returns the maximum element in the sequence.
     * @return CollectionDifferenceChange|null The sequence's maximum element. If the sequence has no elements, it returns null.
     */
    #[Override]
    public function max()
    {
        return $this->sequenceMax();
    }

    /**
     * Returns the result of combining the elements of the sequence using the given closure.
     *
     * Use the {@see reduce()} method to produce a single value from the elements of an entire sequence.
     * For example, you can use this method on an array of integers to filter adjacent equal entries or count frequencies.
     * @template Result
     * @param Result $initialResult The value to use as the initial accumulating value.
     * @param Closure(Result, CollectionDifferenceChange, int=): Result $updateAccumulatingResult A closure that updates the accumulating value with an element of the sequence.
     * @return Result The final accumulated value. If the sequence has no elements, the result is $initialResult.
     */
    #[Override]
    public function reduce(mixed $initialResult, Closure $updateAccumulatingResult)
    {
        return $this->sequenceReduce($initialResult, $updateAccumulatingResult);
    }

    /**
     * Calculate the sum of values in a sequence.
     *
     * Complexity: O(n), where n is the length of the sequence.
     * @return int Returns the sum of values as an integer or float; 0 if the sequence is empty.
     */
    #[Override]
    public function sum(): int
    {
        return $this->reduce(0,
            /**
             * @param int $initialResult
             * @param-out int $initialResult
             * @param CollectionDifferenceChange $change
             * @return int
             */
            fn(int &$initialResult, CollectionDifferenceChange $change) => $initialResult += $change->offset);
    }

    /**
     * @template Result
     * Returns a Collection containing the results of mapping the given closure over the collection's elements.
     * @param Closure(CollectionDifferenceChange, int): Result $transform
     * @return ArrayClass<Result>
     */
    #[Override]
    public function map(Closure $transform): ArrayClass
    {
        return new ArrayClass($this)->map($transform);
    }

    /**
     * @template Result
     * Returns a Collection containing the non-null results of calling the given transformation with each element of this collection.
     *
     * @param Closure(CollectionDifferenceChange, int): ?Result $transform
     * @return ArrayClass<Result>
     */
    #[Override]
    public function compactMap(Closure $transform): ArrayClass
    {
        return new ArrayClass($this)->compactMap($transform);
    }

    /**
     * @template Result
     * Returns a Collection containing the concatenated results of calling the given transformation with each element of this collection.
     * @param Closure(CollectionDifferenceChange, int=): iterable<Result> $transform
     * @return ArrayClass<Result>
     */
    #[Override]
    public function flatMap(Closure $transform): ArrayClass
    {
        return new ArrayClass($this)->flatMap($transform);
    }

    /**
     * Returns the first element of the collection that satisfies the given predicate.
     * @param Closure(CollectionDifferenceChange, int=): bool|null $where A closure that takes an element of the collection as its argument and returns a Boolean value indicating whether the element is a match.
     * @return CollectionDifferenceChange|null The first element of the collection that satisfies predicate or null if there is no element that satisfies predicate.
     */
    #[Override]
    public function first(?Closure $where = null)
    {
        return $this->sequenceFirst($where);
    }

    /**
     * Returns the last element of the collection that satisfies the given predicate.
     * @param Closure(CollectionDifferenceChange, int=): bool|null $where A closure that takes an element of the collection as its argument and returns a Boolean value indicating whether the element is a match.
     * @return CollectionDifferenceChange|null The last element of the collection that satisfies predicate or null if there is no element that satisfies predicate.
     */
    #[Override]
    public function last(?Closure $where = null)
    {
        return $this->bidirectionalCollectionLast($where);
    }

    /**
     * Returns the first index in which an element of the collection satisfies the given predicate.
     * @param Closure(CollectionDifferenceChange): bool $where A closure that takes an element as its argument and returns a Boolean value that indicates whether the passed element represents a match.
     * @return int|null The index of the first element for which the predicate returns true.
     * If no elements in the collection satisfy the given predicate, it returns null.
     */
    #[Override]
    public function firstIndex(Closure $where): ?int
    {
        return $this->collectionFirstIndex($where);
    }

    /**
     * Returns the last index in which an element of the collection satisfies the given predicate.
     * @param Closure(CollectionDifferenceChange): bool $where A closure that takes an element as its argument and returns a Boolean value that indicates whether the passed element represents a match.
     * @return int|null The index of the last element for which the predicate returns true.
     * If no elements in the collection satisfy the given predicate, it returns null.
     */
    #[Override]
    public function lastIndex(Closure $where): ?int
    {
        return $this->bidirectionalCollectionLastIndex($where);
    }

    /**
     * Returns the first index where the specified value appears in the collection.
     * @param CollectionDifferenceChange $element An element to search for in the collection.
     * @return int|null The first index where $element is found. If the $element is not found in the collection, it returns null.
     */
    #[Override]
    public function indexOf(mixed $element): ?int
    {
        return $this->collectionIndexOf($element);
    }

    /**
     * Returns a random element of the collection, using the given generator as a source for randomness.
     * @param RandomNumberGenerator $generator The random number generator to use when choosing a random element.
     * @return CollectionDifferenceChange|null A random element from the collection. If the collection is empty, the method returns null.
     */
    #[Override]
    public function randomElement(RandomNumberGenerator $generator = new SystemRandomNumberGenerator())
    {
        return $this->bidirectionalCollectionRandomElement($generator);
    }

    /**
     * Returns a Collection containing, in order, the elements of the collection that satisfy the given predicate.
     * @param Closure(CollectionDifferenceChange, int=, bool=): bool $isIncluded
     * @return ArrayClass<CollectionDifferenceChange>
     */
    #[Override]
    public function filter(Closure $isIncluded): ArrayClass
    {
        return new ArrayClass($this)->filter($isIncluded);
    }

    /**
     * Evaluates a given predicate against each object in the receiving and returns a new collection containing the objects for which the predicate returns true.
     * @param Predicate $predicate The predicate against which to evaluate the receiving collection's elements.
     * @return ArrayClass<CollectionDifferenceChange> A new collection containing the objects in the receiving array for which predicate returns true.
     * Objects in the resulting array appear in the same order as they do in the receiver.
     */
    #[Override]
    public function filtered(Predicate $predicate): ArrayClass
    {
        return new ArrayClass($this)->filtered($predicate);
    }

    /**
     * Returns a Boolean value indicating whether every element of a sequence satisfies a given predicate.
     * @param Closure(CollectionDifferenceChange, int=): bool $predicate A closure that takes an element of the sequence as its argument and returns a Boolean value that indicates whether the passed element satisfies a condition.
     * @return bool true if the sequence contains an element that satisfies predicate; otherwise, false.
     */
    #[Override]
    public function allSatisfy(Closure $predicate): bool
    {
        return $this->sequenceAllSatisfy($predicate);
    }

    /**
     * Sorts the collection in place.
     * @param Closure(CollectionDifferenceChange, CollectionDifferenceChange): int|null $by
     * @return ArrayClass<CollectionDifferenceChange>
     */
    #[Override]
    public function sort(?Closure $by = null): ArrayClass
    {
        return new ArrayClass($this)->sort($by);
    }

    /**
     * Returns a copy of the receiving collection sorted as specified by a given array of sort descriptors.
     * @param iterable<SortDescriptor> $descriptors A collection of {@see SortDescriptor} objects.
     * @return ArrayClass<CollectionDifferenceChange> A copy of the receiving collection sorted as specified by descriptors.
     */
    #[Override]
    public function sorted(iterable $descriptors): ArrayClass
    {
        return new ArrayClass($this)->sorted($descriptors);
    }

    /**
     * Returns the elements of this sequence of sequences, concatenated.
     * @return FlattenSequence<CollectionDifferenceChange> A flattened view of the elements of this sequence of sequences.
     */
    #[Override]
    public function joined(): FlattenSequence
    {
        return $this->sequenceJoined();
    }

    /**
     * Reverses the elements of the collection in place.
     * @return CollectionDifference
     */
    public function reverse(): CollectionDifference
    {
        return $this->bidirectionalCollectionReverse();
    }

    /**
     * Returns a collection containing the elements of this sequence in reverse order.
     * @return CollectionDifference A collection containing the elements of this sequence in reverse order.
     */
    #[Override]
    public function reversed(): CollectionDifference
    {
        return $this->bidirectionalCollectionReversed();
    }

    /**
     * Adds an element to the end of the collection.
     * @param CollectionDifferenceChange $element
     */
    public function append(mixed $element): void
    {
        $this->reserved[] = $element;
    }

    /**
     * Removes the given element and any elements subsumed by the given element.
     * @param CollectionDifferenceChange $element
     */
    #[Override]
    public function remove(mixed $element): void
    {
        $this->mutableCollectionRemove($element);
    }

    /**
     * Inserts the value into the collection at the specified position.
     *
     * The new element is inserted before the element currently at the specified index.
     * If you pass the collection's endIndex property as the index parameter, the new element is appended to the collection.
     * @param CollectionDifferenceChange $element The new element to insert into the collection.
     * @param int $at The position at which to insert the new element. $at must be a valid index into the collection.
     */
    #[Override]
    public function insertAt(mixed $element, int $at): void
    {
        $this->mutableCollectionInsertAt($element, $at);
    }

    /**
     * @param int $index The index of the member to remove, position must be a valid index of the collection and must not be equal to the collection's end index.
     * @return CollectionDifferenceChange The value that was removed.
     */
    #[Override]
    public function removeAt(int $index)
    {
        return $this->mutableCollectionRemoveAt($index);
    }

    /**
     * Removes all the elements that satisfy the given predicate.
     * @param Closure(CollectionDifferenceChange, int=): bool|null $where A closure that takes an element of the sequence as its argument and returns a Boolean value indicating whether the element should be removed from the collection.
     */
    #[Override]
    public function removeAll(?Closure $where = null): void
    {
        $this->mutableCollectionRemoveAll($where);
    }

    #[Override]
    public function removeFirst(int $k): void
    {
        $this->mutableCollectionRemoveFirst($k);
    }

    #[Override]
    public function removeLast(int $k): void
    {
        $this->mutableCollectionRemoveLast($k);
    }

    /**
     * Removes and returns the first element of the collection.
     * @return CollectionDifferenceChange|null A member of the collection. If the collection is empty, it returns null.
     */
    #[Override]
    public function popFirst()
    {
        return $this->mutableCollectionPopFirst();
    }

    /**
     * Removes and returns the last element of the collection.
     *
     * Calling this method may invalidate all saved indices of this collection. Do not rely on a previously stored index value after altering a collection with any operation that can change its length.
     * @return CollectionDifferenceChange|null The last element of the collection if the collection is not empty; otherwise, null.
     */
    #[Override]
    public function popLast()
    {
        return $this->mutableCollectionPopLast();
    }

    /**
     * Returns a subsequence by skipping elements while predicate returns true and returning the remaining elements.
     * @param Closure(CollectionDifferenceChange): bool $while A closure that takes an element of the sequence as its argument and returns true if the element should be skipped or false if it should be included.
     * Once the predicate returns false, it will not be called again.
     * @return Slice<CollectionDifferenceChange>
     */
    #[Override]
    public function drop(Closure $while): Slice
    {
        return $this->mutableCollectionDrop($while);
    }

    /**
     * Returns a subsequence containing all but the given number of initial elements.
     *
     * If the number of elements to drop exceeds the number of elements in the collection, the result is an empty subsequence.
     * @param int $k The number of elements to drop from the beginning of the collection. $k must be greater than or equal to zero.
     * @return Slice<CollectionDifferenceChange> A subsequence starting after the specified number of elements.
     */
    #[Override]
    public function dropFirst(int $k): Slice
    {
        return $this->mutableCollectionDropFirst($k);
    }

    /**
     * Returns a subsequence containing all but the specified number of final elements.
     *
     * If the number of elements to drop exceeds the number of elements in the collection, the result is an empty subsequence.
     * @param int $k The number of elements to drop off the end of the collection, $k must be greater than or equal to zero.
     * @return Slice<CollectionDifferenceChange> A subsequence that leaves off the specified number of elements at the end.
     */
    #[Override]
    public function dropLast(int $k): Slice
    {
        return $this->mutableCollectionDropLast($k);
    }

    /**
     * @return CollectionDifferenceChange
     */
    #[Override]
    public function current(): mixed
    {
        return $this->iteratorCurrent();
    }

    /**
     * @param int $offset
     * @return bool
     */
    #[Override]
    public function offsetExists(mixed $offset): bool
    {
        return array_key_exists($offset, $this->reserved);
    }

    /**
     * @param int $offset
     * @return CollectionDifferenceChange
     */
    #[Override]
    public function offsetGet(mixed $offset): mixed
    {
        assert(is_int($offset), sprintf("Invalid argument: expecting int, \"%s\"(%s) given", human_readable_value($offset), typeof($offset)));
        if (!$this->offsetExists($offset)) {
            fatal_error(sprintf("%s %s(%s) index \"%s\" out of bounds [%s...<%s]", $this->debugDescription, __FUNCTION__, $offset, $offset, $this->startIndex, $this->endIndex));
        }
        return $this->reserved[$offset];
    }

    /**
     * @param int|null $offset
     * @param CollectionDifferenceChange $value
     */
    #[Override]
    public function offsetSet(mixed $offset, mixed $value): void
    {
        if ($offset === null) {
            $this->reserved[] = $value;
        } else {
            $this->reserved[$offset] = $value;
        }
    }

    /**
     * @param int $offset
     */
    #[Override]
    public function offsetUnset(mixed $offset): void
    {
        if ($this->offsetExists($offset)) {
            unset($this->reserved[$offset]);
        }
    }

    #[Override]
    public function valueForKey(string $key): mixed
    {
        return parent::valueForKey($key);
    }

    #[Override]
    public function setValueForKey(mixed $value, string $key): void
    {
        parent::setValueForKey($value, $key);
    }
}
