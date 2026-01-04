<?php

namespace Sabatier\Foundation;

use ArrayAccess;
use Closure;
use Iterator;
use Override;
use Sabatier\Foundation\Predicates\Predicate;

/**
 * A list of indexes that together represent the path to a specific location in a tree of nested arrays.
 * @implements MutableCollection<int, int>
 * @implements ArrayAccess<int, int>
 * @implements Iterator<int, int>
 */
final class IndexPath extends ObjectClass implements MutableCollection, ArrayAccess, Iterator
{
    use MutableCollectionAlgorithms {
        compare as private sequenceCompare;
        filter as private sequenceFilter;
        allSatisfy as private sequenceAllSatisfy;
        contains as private sequenceContains;
        containsElement as private sequenceContainsElement;
        first as private sequenceFirst;
        min as private sequenceMin;
        max as private sequenceMax;
        reduce as private sequenceReduce;
        firstIndex as private collectionFirstIndex;
        indexOf as private collectionIndexOf;
        filtered as private sequenceFiltered;
        sort as private collectionSort;
        sorted as private collectionSorted;
        joined as private collectionJoined;
        lastIndex as private bidirectionalCollectionLastIndex;
        last as private bidirectionalCollectionLast;
        randomElement as private bidirectionalCollectionRandomElement;
        reverse as private bidirectionalCollectionReverse;
        reversed as private bidirectionalCollectionReversed;
        append as private mutableCollectionAppend;
        appendContentsOf as private mutableCollectionAppendContentsOf;
        insertAt as private mutableCollectionInsertAt;
        insertContentsOf as private mutableCollectionInsertContentsOf;
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

    public string $description {
        get => "[{$this->join(", ")}]";
    }
    public int $count {
        get => count($this->reserved);
    }
    public bool $isEmpty {
        get => $this->count === 0;
    }
    /** @var int|null $first */
    public mixed $first {
        get => $this->first();
    }
    /** @var int|null $last */
    public mixed $last {
        get => $this->last();
    }
    public int $startIndex {
        get => 0;
    }
    public int $endIndex {
        get => $this->count;
    }
    public Range $indices {
        get => new Range($this->startIndex, $this->endIndex);
    }
    /** @var int[] */
    public array $array {
        get => $this->reserved;
    }
    /** @var int An index number identifying a section in a table view or collection view. */
    public int $section {
        get => $this->index(0);
    }
    /** @var int An index number identifying an item in a section of a collection view. */
    public int $item {
        get => $this->index(1);
    }
    /** @var int An index number identifying a row in a section of a table view. */
    public int $row {
        get => $this->item;
    }

    /**
     * Initialized IndexPath object with indexes up to length.
     * @param int[] $indexes Array of indexes to make up the index path.
     */
    public function __construct(array $indexes)
    {
        $this->reserved = $indexes;
    }

    /**
     * Returns a Boolean value indicating whether the sequence contains an element that satisfies the given predicate.
     * @param Closure(int, int): bool $predicate A closure that takes an element of the sequence as its argument and returns a Boolean value that indicates whether the passed element represents a match.
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
     * @param int $element The element to find in the sequence.
     * @return bool true if the element was found in the sequence; otherwise, false.
     */
    #[Override]
    public function containsElement(mixed $element): bool
    {
        return $this->sequenceContainsElement($element);
    }

    /**
     * Returns the minimum element in the sequence.
     * @return int|null The sequence's minimum element. If the sequence has no elements, it returns null.
     */
    #[Override]
    public function min(): ?int
    {
        return $this->sequenceMin();
    }

    /**
     * Returns the maximum element in the sequence.
     * @return int|null The sequence's maximum element. If the sequence has no elements, it returns null.
     */
    #[Override]
    public function max(): ?int
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
     * @param Closure(Result, int, int=): Result $updateAccumulatingResult A closure that updates the accumulating value with an element of the sequence.
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
     * @param Closure(int, int=): Result $transform
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
     * @param Closure(int, int=): Result $transform
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
     * @param Closure(int, int=): iterable<Result> $transform
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
     * @param Closure(int, int=): void $body A closure that takes an element of the sequence as a parameter.
     */
    #[Override]
    public function forEach(Closure $body): void
    {
        foreach (clone $this as $i => $e) {
            $body($e, $i);
        }
    }

    /**
     * Returns the first element of the collection that satisfies the given predicate.
     * @param Closure(int, int=): bool|null $where A closure that takes an element of the collection as its argument and returns a Boolean value indicating whether the element is a match.
     * @return int|null The first element of the collection that satisfies $where, or null if there is no element that satisfies $where.
     */
    #[Override]
    public function first(?Closure $where = null): ?int
    {
        return $this->sequenceFirst($where);
    }

    /**
     * Returns the last element of the collection that satisfies the given predicate.
     * @param Closure(int, int=): bool|null $where A closure that takes an element of the collection as its argument and returns a Boolean value indicating whether the element is a match.
     * @return int|null The last element of the collection that satisfies $where, or null if there is no element that satisfies predicate.
     */
    #[Override]
    public function last(?Closure $where = null): ?int
    {
        return $this->bidirectionalCollectionLast($where);
    }

    /**
     * Returns the first index in which an element of the collection satisfies the given predicate.
     * @param Closure(int, int=): bool $where A closure that takes an element as its argument and returns a Boolean value that indicates whether the passed element represents a match.
     * @return int|null The index of the first element for which $where returns true.
     * If no elements in the collection satisfy the given $where, it returns null.
     */
    #[Override]
    public function firstIndex(Closure $where): ?int
    {
        return $this->collectionFirstIndex($where);
    }

    /**
     * Returns the last index in which an element of the collection satisfies the given predicate.
     * @param Closure(int, int=): bool $where A closure that takes an element as its argument and returns a Boolean value that indicates whether the passed element represents a match.
     * @return int|null The index of the last element for which $where returns true.
     * If no elements in the collection satisfy the given $where, it returns null.
     */
    #[Override]
    public function lastIndex(Closure $where): ?int
    {
        return $this->bidirectionalCollectionLastIndex($where);
    }

    /**
     * Returns the first index where the specified value appears in the collection.
     * @param int $element An element to search for in the collection.
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
     * @return int|null A random element from the collection. If the collection is empty, the method returns null.
     */
    #[Override]
    public function randomElement(RandomNumberGenerator $generator = new SystemRandomNumberGenerator()): ?int
    {
        return $this->bidirectionalCollectionRandomElement($generator);
    }

    /**
     * Returns a Collection containing, in order, the elements of the collection that satisfy the given predicate.
     * @param Closure(int, int=, bool=): bool $isIncluded
     * @return IndexPath
     */
    #[Override]
    public function filter(Closure $isIncluded): IndexPath
    {
        return $this->sequenceFilter($isIncluded);
    }

    /**
     * Evaluates a given predicate against each object in the receiving and returns a new collection containing the objects for which the predicate returns true.
     * @param Predicate $predicate The predicate against which to evaluate the receiving collection's elements.
     * @return IndexPath A new collection containing the objects in the receiving array for which predicate returns true.
     * Objects in the resulting array appear in the same order as they do in the receiver.
     */
    #[Override]
    public function filtered(Predicate $predicate): IndexPath
    {
        return $this->sequenceFiltered($predicate);
    }

    /**
     * Returns a Boolean value indicating whether every element of a sequence satisfies a given predicate.
     * @param Closure(int, int=): bool $predicate A closure that takes an element of the sequence as its argument and returns a Boolean value that indicates whether the passed element satisfies a condition.
     * @return bool true if the sequence contains an element that satisfies predicate; otherwise, false.
     */
    #[Override]
    public function allSatisfy(Closure $predicate): bool
    {
        return $this->sequenceAllSatisfy($predicate);
    }

    /**
     * Sorts the collection in place.
     * @param Closure(int, int=): int|null $by
     * @return IndexPath
     */
    #[Override]
    public function sort(?Closure $by = null): IndexPath
    {
        return $this->collectionSort($by);
    }

    /**
     * Returns a copy of the receiving collection sorted as specified by a given array of sort descriptors.
     * @param iterable<SortDescriptor> $descriptors A collection of {@see SortDescriptor} objects.
     * @return IndexPath A copy of the receiving collection sorted as specified by descriptors.
     */
    #[Override]
    public function sorted(iterable $descriptors): IndexPath
    {
        return $this->collectionSorted($descriptors);
    }

    /**
     * Returns the elements of this sequence of sequences, concatenated.
     * @return FlattenSequence<int> A flattened view of the elements of this sequence of sequences.
     */
    #[Override]
    public function joined(): FlattenSequence
    {
        return $this->collectionJoined();
    }

    /**
     * Reverses the elements of the collection in place.
     * @return IndexPath
     */
    public function reverse(): IndexPath
    {
        return $this->bidirectionalCollectionReverse();
    }

    /**
     * Returns a collection containing the elements of this sequence in reverse order.
     * @return IndexPath A collection containing the elements of this sequence in reverse order.
     */
    #[Override]
    public function reversed(): IndexPath
    {
        return $this->bidirectionalCollectionReversed();
    }

    /**
     * Adds an element to the end of the collection.
     * @param int $element
     */
    #[Override]
    public function append(mixed $element): void
    {
        $this->mutableCollectionAppend($element);
    }

    /**
     * Adds the elements of a sequence or collection to the end of this collection.
     * @param iterable<int> $newElements
     */
    #[Override]
    public function appendContentsOf(iterable $newElements): void
    {
        $this->mutableCollectionAppendContentsOf($newElements);
    }

    /**
     * Removes the given element and any elements subsumed by the given element.
     * @param int $element
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
     * @param int $element The new element to insert into the collection.
     * @param int $at The position at which to insert the new element. $at must be a valid index into the collection.
     */
    #[Override]
    public function insertAt(mixed $element, int $at): void
    {
        $this->mutableCollectionInsertAt($element, $at);
    }

    /**
     * Inserts the elements of a sequence into the collection at the specified position.
     * The new elements are inserted before the element currently at the specified index.
     * If you pass the collection's endIndex property as the index parameter, the new elements are appended to the collection.
     * @param iterable<int, int> $newElements The new elements to insert into the collection.
     * @param int $at The position at which to insert the new elements. $at must be a valid index of the collection.
     */
    #[Override]
    public function insertContentsOf(iterable $newElements, int $at = NotFound): void
    {
        $this->mutableCollectionInsertContentsOf($newElements, $at);
    }

    /**
     * @param int $index The index of the member to remove, position must be a valid index of the collection and must not be equal to the collection's end index.
     * @return int The value that was removed.
     */
    #[Override]
    public function removeAt(int $index): int
    {
        return $this->mutableCollectionRemoveAt($index);
    }

    /**
     * Removes all the elements that satisfy the given predicate.
     * @param Closure(int, int=): bool|null $where A closure that takes an element of the sequence as its argument and returns a Boolean value indicating whether the element should be removed from the collection.
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
     * @return int|null A member of the collection. If the collection is empty, it returns null.
     */
    #[Override]
    public function popFirst(): ?int
    {
        return $this->mutableCollectionPopFirst();
    }

    /**
     * Removes and returns the last element of the collection.
     *
     * Calling this method may invalidate all saved indices of this collection. Do not rely on a previously stored index value after altering a collection with any operation that can change its length.
     * @return int|null The last element of the collection if the collection is not empty; otherwise, null.
     */
    #[Override]
    public function popLast(): ?int
    {
        return $this->mutableCollectionPopLast();
    }

    /**
     * Returns a subsequence by skipping elements while predicate returns true and returning the remaining elements.
     * @param Closure(int, int=): bool $while A closure that takes an element of the sequence as its argument and returns true if the element should be skipped or false if it should be included.
     * Once the predicate returns false, it will not be called again.
     * @return Slice<int>
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
     * @return Slice<int> A subsequence starting after the specified number of elements.
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
     * @param int $k The number of elements to drop off the end of the collection. $k must be greater than or equal to zero.
     * @return Slice<int> A subsequence that leaves off the specified number of elements at the end.
     */
    #[Override]
    public function dropLast(int $k): Slice
    {
        return $this->mutableCollectionDropLast($k);
    }

    /**
     * Returns a new index path containing the elements of this one plus the given element.
     * @param int $index Index to append to the index path's indexes.
     * @return IndexPath A new index path containing the receiving index path's indexes and index.
     */
    public function appending(int $index): IndexPath
    {
        $instance = clone $this;
        $instance->append($index);
        return $instance;
    }

    /**
     * Provides the value at a particular node in the index path.
     * @param int $position Index value of the desired node. Node numbering starts at zero.
     * @return int The index value at node or {@see NotFound} if the node is outside the range of the index path.
     */
    public function index(int $position): int
    {
        return $this->reserved[$position] ?? NotFound;
    }

    /**
     * Copies the indexes stored in the index path from the positions specified by the position range into the specified indexes.
     * @param int[]|null $indexes Array of at least as many ints as specified by the length of $range. On return, the array holds the index path's indexes.
     * @param Range $range A range of valid positions within the index path.
     * @return void
     */
    public function getIndexes(?array &$indexes, Range $range): void
    {
        $indexes = $this->filter($range->contains(...))->array;
    }

    /**
     * @return int
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
     * @return int
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
     * @param int $value
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

    #[Override]
    public function valueForKeyPath(string $keyPath): mixed
    {
        return parent::valueForKeyPath($keyPath);
    }
}
