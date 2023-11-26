<?php

/**
 * Created by PhpStorm.
 * User: dante
 * Date: 29/06/20
 * Time: 05:32
 */

namespace Sabatier\Foundation;

use Closure;
use Iterator;
use Sabatier\Foundation\Predicates\Predicate;

/**
 * An ordered, random-access collection.
 * @template Element
 * @implements RangeReplaceableCollection<Element>
 * @implements Iterator<int, Element>
 * @property-read int $count
 */
class ArrayClass extends ObjectClass implements RangeReplaceableCollection, Iterator
{
    use RangeReplaceableCollectionAlgorithms {
        toArray as private sequenceToArray;
        contains as private sequenceContains;
        containsElement as private sequenceContainsElement;
        first as private sequenceFirst;
        last as private sequenceLast;
        min as private sequenceMin;
        max as private sequenceMax;
        reduce as private sequenceReduce;
        map as private sequenceMap;
        compactMap as private sequenceCompactMap;
        flatMap as private sequenceFlatMap;
        offsetExists as private collectionOffsetExists;
        offsetGet as private collectionOffsetGet;
        offsetSet as private collectionOffsetSet;
        offsetUnset as private collectionOffsetUnset;
        valueForKey as private collectionValueForKey;
        setValueForKey as private collectionSetValueForKey;
        valueForKeyPath as private collectionValueForKeyPath;
        randomElement as private collectionRandomElement;
        firstIndex as private collectionFirstIndex;
        lastIndex as private collectionLastIndex;
        indexOf as private collectionIndexOf;
        filter as private collectionFilter;
        filtered as private collectionFiltered;
        sort as private collectionSort;
        sorted as private collectionSorted;
        allSatisfy as private collectionAllSatisfy;
        joined as private collectionJoined;
        reverse as private bidirectionalCollectionReverse;
        reversed as private bidirectionalCollectionReversed;
        append as private mutableCollectionAppend;
        appendContentsOf as private mutableCollectionAppendContentsOf;
        insert as private mutableCollectionInsert;
        insertAt as private mutableCollectionInsertAt;
        insertContentsOf as private mutableCollectionInsertContentsOf;
        update as private mutableCollectionUpdate;
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
        replaceSubrange as private rangeReplaceableCollectionReplaceSubrange;
    }

    use IteratorAlgorithms {
        current as private iteratorCurrent;
    }

    /**
     * @param iterable<Element> $elements
     */
    public function __construct(iterable $elements = [])
    {
        if ($elements instanceof ArrayClass || $elements instanceof Set) {
            $this->reserved = $elements->toArray();
        } elseif ($elements instanceof Dictionary) {
            $this->reserved = $elements->values->toArray();
        } elseif (is_array($elements)) {
            $this->reserved = is_sequential($elements) ? $elements : array_values($elements);
        } else {
            $this->appendContentsOf($elements);
        }
    }

    public function __get(string $name)
    {
        return match ($name) {
            "count" => $this->count(),
            default => $this->valueForUndefinedKey($name)
        };
    }

    public static function arrayWithArray(array $array): ArrayClass
    {
        return (new ArrayConverter($array))->array;
    }

    /**
     * Returns a Boolean value indicating whether the sequence contains an element that satisfies the given predicate.
     * @param Closure(Element, int): bool $predicate A closure that takes an element of the sequence as its argument and returns a Boolean value that indicates whether the passed element represents a match.
     * @return bool true if the sequence contains an element that satisfies predicate; otherwise, false.
     */
    public function contains(Closure $predicate): bool
    {
        return $this->sequenceContains($predicate);
    }

    /**
     * Returns a bool value indicating whether the sequence contains an element that satisfies the given predicate.
     *
     * Available when Element conforms to {@see Equatable}.
     * @param Element $element The element to find in the sequence.
     * @return bool true if the element was found in the sequence; otherwise, false.
     */
    public function containsElement(mixed $element): bool
    {
        return $this->sequenceContainsElement($element);
    }

    /**
     * Returns the minimum element in the sequence.
     * @return Element|null The sequence's minimum element. If the sequence has no elements, returns nil.
     */
    public function min()
    {
        return $this->sequenceMin();
    }

    /**
     * Returns the maximum element in the sequence.
     * @return Element|null The sequence's maximum element. If the sequence has no elements, returns nil.
     */
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
     * @param Closure(Result, Element, int=): Result $updateAccumulatingResult A closure that updates the accumulating value with an element of the sequence.
     * @return Result The final accumulated value. If the sequence has no elements, the result is initialResult.
     */
    public function reduce(mixed $initialResult, Closure $updateAccumulatingResult)
    {
        return $this->sequenceReduce($initialResult, $updateAccumulatingResult);
    }

    /**
     * @template Result
     * Returns a Collection containing the results of mapping the given closure over the collection's elements.
     * @param Closure(Element, int): Result $transform
     * @return ArrayClass<Result>
     */
    public function map(Closure $transform): ArrayClass
    {
        return $this->sequenceMap($transform);
    }

    /**
     * @template Result
     * Returns a Collection containing the non-nil results of calling the given transformation with each element of this collection.
     * @param Closure(Element, int): Result $transform
     * @return ArrayClass<Result>
     */
    public function compactMap(Closure $transform): ArrayClass
    {
        return $this->sequenceCompactMap($transform);
    }

    /**
     * @template Result
     * Returns a Collection containing the concatenated results of calling the given transformation with each element of this collection.
     * @param Closure(Element, int=): iterable<Result> $transform
     * @return ArrayClass<Result>
     */
    public function flatMap(Closure $transform): ArrayClass
    {
        return $this->sequenceFlatMap($transform);
    }

    /**
     * Returns the first element of the collection that satisfies the given predicate.
     * @param Closure(Element, int=): bool|null $where A closure that takes an element of the collection as its argument and returns a Boolean value indicating whether the element is a match.
     * @return Element|null The first element of the collection that satisfies predicate, or nil if there is no element that satisfies predicate.
     */
    public function first(Closure $where = null)
    {
        return $this->sequenceFirst($where);
    }

    /**
     * Returns the last element of the collection that satisfies the given predicate.
     * @param Closure(Element, int=): bool|null $where A closure that takes an element of the collection as its argument and returns a Boolean value indicating whether the element is a match.
     * @return Element|null The last element of the collection that satisfies predicate, or nil if there is no element that satisfies predicate.
     */
    public function last(Closure $where = null)
    {
        return $this->sequenceLast($where);
    }

    /**
     * Returns the first index in which an element of the collection satisfies the given predicate.
     * @param Closure(Element): bool $where A closure that takes an element as its argument and returns a Boolean value that indicates whether the passed element represents a match.
     * @return int|null The index of the first element for which predicate returns true.
     * If no elements in the collection satisfy the given predicate, returns nil.
     */
    public function firstIndex(Closure $where): ?int
    {
        return $this->collectionFirstIndex($where);
    }

    /**
     * Returns the last index in which an element of the collection satisfies the given predicate.
     * @param Closure(Element): bool $where A closure that takes an element as its argument and returns a Boolean value that indicates whether the passed element represents a match.
     * @return int|null The index of the last element for which predicate returns true.
     * If no elements in the collection satisfy the given predicate, returns nil.
     */
    public function lastIndex(Closure $where): ?int
    {
        return $this->collectionLastIndex($where);
    }

    /**
     * Returns the first index where the specified value appears in the collection.
     * @param Element $element An element to search for in the collection.
     * @return int|null The first index where element is found. If element is not found in the collection, returns nil.
     */
    public function indexOf(mixed $element): ?int
    {
        return $this->collectionIndexOf($element);
    }

    /**
     * Returns a random element of the collection, using the given generator as a source for randomness.
     * @param RandomNumberGenerator $generator The random number generator to use when choosing a random element.
     * @return Element|null A random element from the collection. If the collection is empty, the method returns nil.
     */
    public function randomElement(RandomNumberGenerator $generator = new SystemRandomNumberGenerator())
    {
        return $this->collectionRandomElement($generator);
    }

    /**
     * Returns a Collection containing, in order, the elements of the collection that satisfy the given predicate.
     * @param Closure(Element, int=, bool=): bool $isIncluded
     * @return ArrayClass<Element>
     */
    public function filter(Closure $isIncluded): ArrayClass
    {
        return $this->collectionFilter($isIncluded);
    }

    /**
     * Evaluates a given predicate against each object in the receiving and returns a new collection containing the objects for which the predicate returns true.
     * @param Predicate $predicate The predicate against which to evaluate the receiving collection's elements.
     * @return ArrayClass<Element> A new collection containing the objects in the receiving array for which predicate returns true.
     * Objects in the resulting array appear in the same order as they do in the receiver.
     */
    public function filtered(Predicate $predicate): ArrayClass
    {
        return $this->collectionFiltered($predicate);
    }

    /**
     * Returns a Boolean value indicating whether every element of a sequence satisfies a given predicate.
     * @param Closure(Element, int=): bool $predicate A closure that takes an element of the sequence as its argument and returns a Boolean value that indicates whether the passed element satisfies a condition.
     * @return bool true if the sequence contains an element that satisfies predicate; otherwise, false.
     */
    public function allSatisfy(Closure $predicate): bool
    {
        return $this->collectionAllSatisfy($predicate);
    }

    /**
     * Sorts the collection in place.
     * @param Closure(Element, Element): int|null $by
     * @return ArrayClass<Element>
     */
    public function sort(?Closure $by = null): ArrayClass
    {
        return $this->collectionSort($by);
    }

    /**
     * Returns a copy of the receiving collection sorted as specified by a given array of sort descriptors.
     * @param iterable<SortDescriptor> $descriptors A collection of {@see SortDescriptor} objects.
     * @return ArrayClass<Element> A copy of the receiving collection sorted as specified by descriptors.
     */
    public function sorted(iterable $descriptors): ArrayClass
    {
        return $this->collectionSorted($descriptors);
    }

    /**
     * Returns the elements of this sequence of sequences, concatenated.
     * @return FlattenSequence<Element> A flattened view of the elements of this sequence of sequences.
     * @psalm-suppress LessSpecificReturnStatement, MoreSpecificReturnType
     */
    public function joined(): FlattenSequence
    {
        return $this->collectionJoined();
    }

    /**
     * Returns an array containing the results of invoking {@see KeyValueCoding::valueForKey()} using key on each of the array's objects.
     * @param string $key The key to retrieve.
     * @return ArrayClass The value of the retrieved key.
     */
    public function valueForKey(string $key): ArrayClass
    {
        return $this->collectionValueForKey($key);
    }

    /**
     * Invokes {@see KeyValueCoding::setValueForKey()} on each of the array's items using the specified value and key.
     * @param mixed $value The object value.
     * @param string $key The key to store the value.
     */
    public function setValueForKey(mixed $value, string $key): void
    {
        $this->collectionSetValueForKey($value, $key);
    }

    /**
     * Reverses the elements of the collection in place.
     * @return ArrayClass<Element>
     */
    public function reverse(): ArrayClass
    {
        return $this->bidirectionalCollectionReverse();
    }

    /**
     * Returns a collection containing the elements of this sequence in reverse order.
     * @return ArrayClass<Element> A collection containing the elements of this sequence in reverse order.
     */
    public function reversed(): ArrayClass
    {
        return $this->bidirectionalCollectionReversed();
    }

    /**
     * Adds an element to the end of the collection.
     * @param Element $element
     */
    public function append(mixed $element): void
    {
        $this->mutableCollectionAppend($element);
    }

    /**
     * Adds the elements of a sequence or collection to the end of this collection.
     * @param iterable<Element> $newElements
     */
    public function appendContentsOf(iterable $newElements): void
    {
        $this->mutableCollectionAppendContentsOf($newElements);
    }

    /**
     * Removes the given element and any elements subsumed by the given element.
     * @param Element $element
     */
    public function remove(mixed $element): void
    {
        $this->mutableCollectionRemove($element);
    }

    /**
     * Inserts the given element in the collection if it is not already present.
     *
     * If an element equal to newElement is already contained in the collection, this method has no effect.
     * @param Element $newElement An element to insert into the collection.
     * @return array{inserted: boolean, elementAfterInsert: Element} (true, newElement) if newElement was not contained in the collection. If an element equal to newElement was already contained in the collection, the method returns (false, oldElement), where oldElement is the element that was equal to newElement. In some cases, oldElement may be distinguishable from newElement by identity comparison or some other means.
     */
    public function insert(mixed $newElement): array
    {
        return $this->mutableCollectionInsert($newElement);
    }

    /**
     * Inserts the value into the collection at the specified position.
     *
     * The new element is inserted before the element currently at the specified index.
     * If you pass the collection's endIndex property as the index parameter, the new element is appended to the collection.
     * @param Element $element The new element to insert into the collection.
     * @param int $at The position at which to insert the new element. index must be a valid index into the collection.
     */
    public function insertAt(mixed $element, int $at): void
    {
        $this->mutableCollectionInsertAt($element, $at);
    }

    /**
     * Inserts the elements of a sequence into the collection at the specified position.
     * The new elements are inserted before the element currently at the specified index.
     * If you pass the collection's endIndex property as the index parameter, the new elements are appended to the collection.
     * @param iterable<int, Element> $newElements The new elements to insert into the collection.
     * @param int $at The position at which to insert the new elements. index must be a valid index of the collection.
     */
    public function insertContentsOf(iterable $newElements, int $at = NotFound): void
    {
        $this->mutableCollectionInsertContentsOf($newElements, $at);
    }

    /**
     * Inserts the given element into the collection unconditionally.
     * If an element equal to newElement is already contained in the collection, newElement replaces the existing element.
     * @param Element $element An element to insert into the collection.
     * @return Element|null An element equal to newElement if the collection already contained such a member; otherwise, nil.
     */
    public function update(mixed $element)
    {
        return $this->mutableCollectionUpdate($element);
    }

    /**
     * @param int $index The index of the member to remove, position must be a valid index of the collection, and must not be equal to the collection's end index.
     * @return Element The value that was removed.
     */
    public function removeAt(int $index)
    {
        return $this->mutableCollectionRemoveAt($index);
    }

    /**
     * Removes all the elements that satisfy the given predicate.
     * @param Closure(Element, int=): bool|null $where A closure that takes an element of the sequence as its argument and returns a Boolean value indicating whether the element should be removed from the collection.
     */
    public function removeAll(Closure $where = null): void
    {
        $this->mutableCollectionRemoveAll($where);
    }

    public function removeFirst(int $k): void
    {
        $this->mutableCollectionRemoveFirst($k);
    }

    public function removeLast(int $k): void
    {
        $this->mutableCollectionRemoveLast($k);
    }

    /**
     * Removes and returns the first element of the collection.
     * @return Element|null A member of the collection. If the collection is empty, returns nil.
     */
    public function popFirst()
    {
        return $this->mutableCollectionPopFirst();
    }

    /**
     * Removes and returns the last element of the collection.
     *
     * Calling this method may invalidate all saved indices of this collection. Do not rely on a previously stored index value after altering a collection with any operation that can change its length.
     * @return Element|null The last element of the collection if the collection is not empty; otherwise, nil.
     */
    public function popLast()
    {
        return $this->mutableCollectionPopLast();
    }

    /**
     * Returns a subsequence by skipping elements while predicate returns true and returning the remaining elements.
     * @param Closure(Element): bool $while A closure that takes an element of the sequence as its argument and returns true if the element should be skipped or false if it should be included.
     * Once the predicate returns false it will not be called again.
     * @return Slice<Element>
     */
    public function drop(Closure $while): Slice
    {
        return $this->mutableCollectionDrop($while);
    }

    /**
     * Returns a subsequence containing all but the given number of initial elements.
     *
     * If the number of elements to drop exceeds the number of elements in the collection, the result is an empty subsequence.
     * @param int $k The number of elements to drop from the beginning of the collection. k must be greater than or equal to zero.
     * @return Slice<Element> A subsequence starting after the specified number of elements.
     */
    public function dropFirst(int $k): Slice
    {
        return $this->mutableCollectionDropFirst($k);
    }

    /**
     * Returns a subsequence containing all but the specified number of final elements.
     *
     * If the number of elements to drop exceeds the number of elements in the collection, the result is an empty subsequence.
     * @param int $k The number of elements to drop off the end of the collection. k must be greater than or equal to zero.
     * @return Slice<Element> A subsequence that leaves off the specified number of elements at the end.
     */
    public function dropLast(int $k): Slice
    {
        return $this->mutableCollectionDropLast($k);
    }

    /**
     * This method has the effect of removing the specified range of elements from the collection and inserting the new elements at the same location.
     *
     * The number of new elements need not match the number of elements being removed.
     * @param Range $subrange The subrange of the collection to replace. The start and end of a subrange must be valid indices of the collection.
     * @param Collection<int, Element> $newElements The new elements to add to the collection.
     */
    public function replaceSubrange(Range $subrange, Collection $newElements): void
    {
        /** @psalm-suppress InvalidArgument */
        $this->rangeReplaceableCollectionReplaceSubrange($subrange, $newElements);
    }

    /**
     * Returns the longest possible subsequences of the collection, in order, that don't contain elements satisfying the given predicate.
     * @param Closure(Element): bool $isSeparator A closure that takes an element as an argument and returns a Boolean value indicating whether the collection should be split at that element.
     * @param int $maxSplits The maximum number of times to split the collection, or one less than the number of subsequences to return. If maxSplits + 1 subsequences are returned, the last one is a suffix of the original collection containing the remaining elements. maxSplits must be greater than or equal to zero. The default value is Int.max.
     * @param bool $omittingEmptySubsequences If false, an empty subsequence is returned in the result for each pair of consecutive elements satisfying the isSeparator predicate and for each element at the start or end of the collection satisfying the isSeparator predicate. The default value is true.
     * @return ArrayClass<Slice<Element>> An array of subsequences, split from this collection's elements.
     */
    public function split(Closure $isSeparator, int $maxSplits = PHP_INT_MAX, bool $omittingEmptySubsequences = true): ArrayClass
    {
        /** @var ArrayClass<Slice<Element>> $result */
        $result = new ArrayClass();
        $subSequenceStart = $this->startIndex();
        $appendSubsequence = (function (int $end) use ($result, &$subSequenceStart, $omittingEmptySubsequences): bool {
            if ($subSequenceStart === $end && $omittingEmptySubsequences) {
                return false;
            }
            /** @psalm-suppress InvalidArgument */
            $result->append(new Slice($this, new Range($subSequenceStart, $end)));
            return true;
        });
        if ($maxSplits === 0 || $this->isEmpty()) {
            $appendSubsequence($this->endIndex());
            return $result;
        }
        $subSequenceEnd = $subSequenceStart;
        $cachedEndIndex = $this->endIndex();
        while ($subSequenceEnd != $cachedEndIndex) {
            if ($isSeparator($this[$subSequenceEnd])) {
                $didAppend = $appendSubsequence($subSequenceEnd);
                $this->formIndexAfter($subSequenceEnd);
                $subSequenceStart = $subSequenceEnd;
                if ($didAppend && $result->count() === $maxSplits) {
                    break;
                }
                continue;
            }
            $this->formIndexAfter($subSequenceEnd);
        }
        if ($subSequenceStart != $cachedEndIndex || !$omittingEmptySubsequences) {
            /** @psalm-suppress InvalidArgument */
            $result->append(new Slice($this, new Range($subSequenceStart, $cachedEndIndex)));
        }
        return $result;
    }

    /**
     * Returns the longest possible subsequences of the collection, in order, around elements equal to the given element.
     *
     * Available when Element conforms to {@see Equatable}.
     * @param Element $separator The element that should be split upon.
     * @param int $maxSplits The maximum number of times to split the collection, or one less than the number of subsequences to return. If maxSplits + 1 subsequences are returned, the last one is a suffix of the original collection containing the remaining elements. maxSplits must be greater than or equal to zero. The default value is PHP_INT_MAX.
     * @param bool $omittingEmptySubsequences If false, an empty subsequence is returned in the result for each consecutive pair of separator elements in the collection and for each instance of separator at the start or end of the collection. If true, only nonempty subsequences are returned. The default value is true.
     * @return ArrayClass<Slice<Element>> An array of subsequences, split from this collection's elements.
     */
    public function separate(mixed $separator, int $maxSplits = PHP_INT_MAX, bool $omittingEmptySubsequences = true): ArrayClass
    {
        return $this->split(fn(mixed $e): bool => $e instanceof Equatable ? $e->isEqual($separator) : $e === $separator, $maxSplits, $omittingEmptySubsequences);
    }

    /**
     * Shuffles the collection in place, using the given generator as a source for randomness.
     * @param RandomNumberGenerator $generator The random number generator to use when shuffling the collection.
     */
    public function shuffle(RandomNumberGenerator $generator = new SystemRandomNumberGenerator()): void
    {
        $max = $this->indexBefore($this->endIndex());
        for ($i = 0; $i <= $max; $i++) {
            $this->swapAt($i, $i + $generator->next($max - $i));
        }
    }

    /**
     * Returns the elements of the sequence, shuffled using the given generator as a source for randomness.
     * @param RandomNumberGenerator $generator The random number generator to use when shuffling the sequence.
     * @return ArrayClass<Element> An array of this sequence's elements in a shuffled order.
     */
    public function shuffled(RandomNumberGenerator $generator = new SystemRandomNumberGenerator()): ArrayClass
    {
        $instance = clone $this;
        $instance->shuffle($generator);
        return $instance;
    }

    /**
     * Reorders the elements of the collection such that all the elements that match the given predicate are after all the elements that don't match.
     * @param Closure(Element): bool $belongsInSecondPartition A predicate used to partition the collection. All elements satisfying this predicate are ordered after all elements not satisfying it.
     * @return int The index of the first element in the reordered collection that matches belongsInSecondPartition. If no elements in the collection match $belongsInSecondPartition, the returned index is equal to the collection's endIndex.
     */
    public function partition(Closure $belongsInSecondPartition): int
    {
        $second = $this->filter($belongsInSecondPartition);
        $this->removeAll($belongsInSecondPartition);
        $this->appendContentsOf($second);
        return $this->count() - $second->count();
    }

    /**
     * Exchanges the values at the specified indices of the collection.
     *
     * Both parameters must be valid indices of the collection that are not equal to endIndex. Calling swapAt() with the same index as both i and j has no effect.
     * @param int $i The index of the first value to swap.
     * @param int $j The index of the second value to swap.
     */
    public function swapAt(int $i, int $j): void
    {
        if ($i === $j) {
            return;
        }
        $temp = $this[$i];
        $this[$i] = $this[$j];
        $this[$j] = $temp;
    }

    /**
     * Returns a Boolean value indicating whether the initial elements of the sequence are equivalent to the elements in another sequence, using the given predicate as the equivalence test.
     * @param Sequence&Iterator $possiblePrefix A sequence to compare to this sequence.
     * @param Closure(Element, Element): bool|null $areEquivalent A predicate that returns true if its two arguments are equivalent; otherwise, false.
     * @return bool true if the initial elements of the sequence are equivalent to the elements of possiblePrefix; otherwise, false. If possiblePrefix has no elements, the return value is true.
     */
    public function starts(Sequence $possiblePrefix, ?Closure $areEquivalent = null): bool
    {
        $areEquivalent ??= fn(mixed $e0, mixed $e1): bool => is_equal($e0, $e1);
        foreach ($this as $e0) {
            if ($possiblePrefix->valid()) {
                if (!$areEquivalent($e0, $possiblePrefix->current())) {
                    return false;
                }
            } else {
                return true;
            }
            $possiblePrefix->next();
        }
        return $possiblePrefix->valid();
    }

    /**
     * Returns a Boolean value indicating whether the sequence precedes another sequence in a lexicographical (dictionary) ordering, using the given predicate to compare elements.
     *
     * This method implements the mathematical notion of lexicographical ordering, which has no connection to Unicode. If you are sorting strings to present to the end user, use String APIs that perform localized comparison.
     * @param Sequence&Iterator $other A sequence to compare to this sequence.
     * @param Closure(Element, Element): bool|null $areInIncreasingOrder A predicate that returns true if its first argument should be ordered before its second argument; otherwise, false.
     * @return bool true if this sequence precedes other in a dictionary ordering as ordered by areInIncreasingOrder; otherwise, false.
     */
    public function lexicographicallyPrecedes(Sequence $other, ?Closure $areInIncreasingOrder = null): bool
    {
        $areInIncreasingOrder ??= fn(mixed $e0, mixed $e1): bool => $e0 < $e1;
        while (true) {
            if ($this->valid()) {
                $e0 = $this->current();
                if ($other->valid()) {
                    $e1 = $other->current();
                    if ($areInIncreasingOrder($e0, $e1)) {
                        return true;
                    }
                    if ($areInIncreasingOrder($e1, $e0)) {
                        return false;
                    }
                    $this->next();
                    $other->next();
                    continue; //Equivalent
                }
                return false;
            }
            return $other->valid();
        }
    }

    /**
     * @param ArrayClass<Element> $array
     */
    public function setArray(ArrayClass $array): void
    {
        $this->reserved = $array->toArray();
    }

    /**
     * @return Element[]
     */
    public function toArray(): array
    {
        return $this->sequenceToArray();
    }

    public function description(): string
    {
        return "[" . $this->join(", ") . "]";
    }

    /**
     * @return Element
     */
    public function current(): mixed
    {
        return $this->iteratorCurrent();
    }

    /**
     * @param int $offset
     * @return bool
     */
    public function offsetExists(mixed $offset): bool
    {
        return $this->collectionOffsetExists($offset);
    }

    /**
     * @param int $offset
     * @return Element
     */
    public function offsetGet(mixed $offset): mixed
    {
        return $this->collectionOffsetGet($offset);
    }

    /**
     * @param int|null $offset
     * @param Element $value
     */
    public function offsetSet(mixed $offset, mixed $value): void
    {
        $this->collectionOffsetSet($offset, $value);
    }

    /**
     * @param int $offset
     */
    public function offsetUnset(mixed $offset): void
    {
        $this->collectionOffsetUnset($offset);
    }
}
