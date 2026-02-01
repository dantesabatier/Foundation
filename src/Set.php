<?php

/**
 * Created by PhpStorm.
 * User: dante
 * Date: 17/06/20
 * Time: 07:11
 */

namespace Sabatier\Foundation;

use ArrayAccess;
use Closure;
use Iterator;
use Override;
use Sabatier\Foundation\Predicates\Predicate;

/**
 * An unordered collection of unique elements.
 * @template Element
 * @implements SetAlgebra<Element>
 * @implements Iterator<int, Element>
 * @implements ArrayAccess<int, Element>
 */
class Set extends ObjectClass implements SetAlgebra, ArrayAccess, Iterator
{
    use SetAlgebraAlgorithms {
        filter as private sequenceFilter;
        allSatisfy as private sequenceAllSatisfy;
        contains as private sequenceContains;
        containsElement as private sequenceContainsElement;
        first as private sequenceFirst;
        min as private sequenceMin;
        max as private sequenceMax;
        reduce as private sequenceReduce;
        map as private sequenceMap;
        compactMap as private sequenceCompactMap;
        flatMap as private sequenceFlatMap;
        joined as private sequenceJoined;
        valueForKey as private collectionValueForKey;
        setValueForKey as private collectionSetValueForKey;
        valueForKeyPath as private collectionValueForKeyPath;
        firstIndex as private collectionFirstIndex;
        indexOf as private collectionIndexOf;
        filtered as private sequenceFiltered;
        sort as private collectionSort;
        sorted as private collectionSorted;
        last as private bidirectionalCollectionLast;
        lastIndex as private bidirectionalCollectionLastIndex;
        randomElement as private bidirectionalCollectionRandomElement;
        reverse as private bidirectionalCollectionReverse;
        reversed as private bidirectionalCollectionReversed;
        insertAt as private mutableCollectionInsertAt;
        removeAt as private mutableCollectionRemoveAt;
        removeFirst as private mutableCollectionRemoveFirst;
        removeLast as private mutableCollectionRemoveLast;
        removeAll as private mutableCollectionRemoveAll;
        drop as private mutableCollectionDrop;
        dropFirst as private mutableCollectionDropFirst;
        dropLast as private mutableCollectionDropLast;
        popFirst as private mutableCollectionPopFirst;
        popLast as private mutableCollectionPopLast;
        insert as private setAlgebraInsert;
        update as private setAlgebraUpdate;
        remove as private setAlgebraRemove;
        member as private setAlgebraMember;
        union as private setAlgebraUnion;
        formUnion as private setAlgebraFormUnion;
        intersection as private setAlgebraIntersection;
        formIntersection as private setAlgebraFormIntersection;
        symmetricDifference as private setAlgebraSymmetricDifference;
        formSymmetricDifference as private setAlgebraFormSymmetricDifference;
        subtract as private setAlgebraSubtract;
        subtracting as private setAlgebraSubtracting;
        isSubset as private setAlgebraIsSubset;
        isSuperset as private setAlgebraIsSuperset;
        isDisjoint as private setAlgebraIsDisjoint;
    }

    use IteratorAlgorithms {
        current as private iteratorCurrent;
    }

    public int $count {
        get => count($this->reserved);
    }
    public bool $isEmpty {
        get => $this->count === 0;
    }
    /** @var Element|null $first */
    public mixed $first {
        get => $this->first();
    }
    /** @var Element|null $last */
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
    /** @var list<Element> */
    public array $array {
        get => $this->reserved;
    }
    public string $description {
        get => "[{$this->join(", ")}]";
    }

    /**
     * @param iterable<int, Element> $elements
     */
    public function __construct(iterable $elements = [])
    {
        if ($elements instanceof Set) {
            $this->reserved = $elements->array;
        } else {
            $this->formUnion($elements);
        }
    }

    /**
     * Returns a Boolean value indicating whether the sequence contains an element that satisfies the given predicate.
     * @param Closure(Element, int): bool $predicate A closure that takes an element of the sequence as its argument and returns a Boolean value that indicates whether the passed element represents a match.
     * @return bool true if the sequence contains an element that satisfies predicate; otherwise, false.
     */
    #[Override]
    public function contains(Closure $predicate): bool
    {
        return $this->sequenceContains($predicate);
    }

    /**
     * Returns a bool value indicating whether the sequence contains an element that satisfies the given predicate.
     * Available when Element conforms to {@see Equatable}.
     * @param Element $element The element to find in the sequence.
     * @return bool true if the element was found in the sequence; otherwise, false.
     */
    #[Override]
    public function containsElement(mixed $element): bool
    {
        return $this->sequenceContainsElement($element);
    }

    /**
     * Returns the minimum element in the sequence.
     * @return Element|null The sequence's minimum element. If the sequence has no elements, it returns null.
     */
    #[Override]
    public function min()
    {
        return $this->sequenceMin();
    }

    /**
     * Returns the maximum element in the sequence.
     * @return Element|null The sequence's maximum element. If the sequence has no elements, it returns null.
     */
    #[Override]
    public function max()
    {
        return $this->sequenceMax();
    }

    /**
     * Returns the result of combining the elements of the sequence using the given closure.
     * Use the {@see reduce()} method to produce a single value from the elements of an entire sequence.
     * For example, you can use this method on an array of integers to filter adjacent equal entries or count frequencies.
     * @template Result
     * @param Result $initialResult The value to use as the initial accumulating value.
     * @param Closure(Result, Element, int=): Result $updateAccumulatingResult A closure that updates the accumulating value with an element of the sequence.
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
     * @param Closure(Element, int=): Result $transform
     * @return Set<Result>
     */
    #[Override]
    public function map(Closure $transform): Set
    {
        return $this->sequenceMap($transform);
    }

    /**
     * @template Result
     * Returns a Collection containing the non-null results of calling the given transformation with each element of this collection.
     *
     * @param Closure(Element, int=): ?Result $transform
     * @return Set<Result>
     */
    #[Override]
    public function compactMap(Closure $transform): Set
    {
        return $this->sequenceCompactMap($transform);
    }

    /**
     * @template Result
     * Returns a Sequence containing the concatenated results of calling the given transformation with each element of this Sequence.
     * @param Closure(Element, int=): iterable<Result> $transform
     * @return Set<Result>
     */
    #[Override]
    public function flatMap(Closure $transform): Set
    {
        return $this->sequenceFlatMap($transform);
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
     * Returns the first element of the collection that satisfies the given predicate.
     * @param Closure(Element, int=): bool|null $where A closure that takes an element of the collection as its argument and returns a Boolean value indicating whether the element is a match.
     * @return Element|null The first element of the collection that satisfies predicate or null if there is no element that satisfies predicate.
     */
    #[Override]
    public function first(?Closure $where = null)
    {
        return $this->sequenceFirst($where);
    }

    /**
     * Returns the last element of the collection that satisfies the given predicate.
     * @param Closure(Element, int=): bool|null $where A closure that takes an element of the collection as its argument and returns a Boolean value indicating whether the element is a match.
     * @return Element|null The last element of the collection that satisfies predicate or null if there is no element that satisfies predicate.
     */
    #[Override]
    public function last(?Closure $where = null)
    {
        return $this->bidirectionalCollectionLast($where);
    }

    /**
     * Returns the first index in which an element of the collection satisfies the given predicate.
     * @param Closure(Element): bool $where A closure that takes an element as its argument and returns a Boolean value that indicates whether the passed element represents a match.
     * @return int|null The index of the first element for which $where returns true.
     * If no elements in the collection satisfy the given $where returns null.
     */
    #[Override]
    public function firstIndex(Closure $where): ?int
    {
        return $this->collectionFirstIndex($where);
    }

    /**
     * Returns the last index in which an element of the collection satisfies the given predicate.
     * @param Closure(Element): bool $where A closure that takes an element as its argument and returns a Boolean value that indicates whether the passed element represents a match.
     * @return int|null The index of the last element for which $where returns true.
     * If no elements in the collection satisfy the given $where returns null.
     */
    #[Override]
    public function lastIndex(Closure $where): ?int
    {
        return $this->bidirectionalCollectionLastIndex($where);
    }

    /**
     * Returns the first index where the specified value appears in the collection.
     * @param Element $element An element to search for in the collection.
     * @return int|null The first index where $element is found. If the element is not found in the collection, it returns null.
     */
    #[Override]
    public function indexOf(mixed $element): ?int
    {
        return $this->collectionIndexOf($element);
    }

    /**
     * Returns a random element of the collection, using the given generator as a source for randomness.
     * @param RandomNumberGenerator $generator The random number generator to use when choosing a random element.
     * @return Element|null A random element from the collection. If the collection is empty, the method returns null.
     */
    #[Override]
    public function randomElement(RandomNumberGenerator $generator = new SystemRandomNumberGenerator())
    {
        return $this->bidirectionalCollectionRandomElement($generator);
    }

    /**
     * Returns a Collection containing, in order, the elements of the collection that satisfy the given predicate.
     * @param Closure(Element, int=, bool=): bool $isIncluded
     * @return Set<Element>
     */
    #[Override]
    public function filter(Closure $isIncluded): Set
    {
        return $this->sequenceFilter($isIncluded);
    }

    /**
     * Evaluates a given predicate against each object in the receiving and returns a new collection containing the objects for which the predicate returns true.
     * @param Predicate $predicate The predicate against which to evaluate the receiving collection's elements.
     * @return Set<Element> A new collection containing the objects in the receiving array for which predicate returns true.
     * Objects in the resulting array appear in the same order as they do in the receiver.
     */
    #[Override]
    public function filtered(Predicate $predicate): Set
    {
        return $this->sequenceFiltered($predicate);
    }

    /**
     * Sorts the collection in place.
     * @param Closure(Element, Element): int|null $by
     * @return Set<Element>
     */
    #[Override]
    public function sort(?Closure $by = null): Set
    {
        return $this->collectionSort($by);
    }

    /**
     * Returns a copy of the receiving sequence sorted as specified by a given array of sort descriptors.
     * @param iterable<SortDescriptor> $descriptors A sequence of {@see SortDescriptor} objects.
     * @return Set<Element> A copy of the receiving sequence sorted as specified by descriptors.
     */
    #[Override]
    public function sorted(iterable $descriptors): Set
    {
        return $this->collectionSorted($descriptors);
    }

    /**
     * Returns the elements of the sequence, shuffled.
     * @return ArrayClass<Element> A shuffled array of this sequence's elements.
     */
    public function shuffled(RandomNumberGenerator $generator): ArrayClass
    {
        return new ArrayClass($this)->shuffled($generator);
    }

    /**
     * Returns a Boolean value indicating whether every element of a sequence satisfies a given predicate.
     * @param Closure(Element, int=): bool $predicate A closure that takes an element of the sequence as its argument and returns a Boolean value that indicates whether the passed element satisfies a condition.
     * @return bool true if the sequence contains an element that satisfies predicate; otherwise, false.
     */
    #[Override]
    public function allSatisfy(Closure $predicate): bool
    {
        return $this->sequenceAllSatisfy($predicate);
    }

    /**
     * Returns the elements of this sequence of sequences, concatenated.
     * @return FlattenSequence<Element> A flattened view of the elements of this sequence of sequences.
     */
    #[Override]
    public function joined(): FlattenSequence
    {
        return $this->sequenceJoined();
    }

    /**
     * Return a set containing the results of invoking {@see KeyValueCoding::valueForKey()} on each of the receiving set's members.
     *
     * The returned set might not have the same number of members as the receiving set. The returned set will not contain any elements corresponding to instances of valueForKey() returning null.
     * @param string $key The name of one of the properties of the receiving set's members.
     * @return Set A set containing the results of invoking {@see KeyValueCoding::valueForKey()} (with the argument key) on each of the receiving set's members.
     */
    #[Override]
    public function valueForKey(string $key): Set
    {
        return $this->collectionValueForKey($key);
    }

    /**
     * Invokes {@see KeyValueCoding::setValueForKey()} on each of the set's members.
     * @param mixed $value The value for the property identified by $key.
     * @param string $key The name of one of the properties of the set's members.
     */
    #[Override]
    public function setValueForKey(mixed $value, string $key): void
    {
        $this->collectionSetValueForKey($value, $key);
    }

    /**
     * Reverses the elements of the collection in place.
     * @return Set<Element>
     */
    public function reverse(): Set
    {
        return $this->bidirectionalCollectionReverse();
    }

    /**
     * Returns a collection containing the elements of this sequence in reverse order.
     * @return Set<Element> A collection containing the elements of this sequence in reverse order.
     */
    #[Override]
    public function reversed(): Set
    {
        return $this->bidirectionalCollectionReversed();
    }

    /**
     * Inserts the value into the collection at the specified position.
     *
     * The new element is inserted before the element currently at the specified index.
     * If you pass the collection's endIndex property as the index parameter, the new element is appended to the collection.
     * @param Element $element The new element to insert into the collection.
     * @param int $at The position at which to insert the new element. The $at must be a valid index into the collection.
     */
    #[Override]
    public function insertAt(mixed $element, int $at): void
    {
        if (!$this->containsElement($element)) {
            $this->mutableCollectionInsertAt($element, $at);
        }
    }

    /**
     * @param int $index The index of the member to remove, position must be a valid index of the collection and must not be equal to the collection's end index.
     * @return Element The value that was removed.
     */
    #[Override]
    public function removeAt(int $index)
    {
        return $this->mutableCollectionRemoveAt($index);
    }

    /**
     * Removes all the elements that satisfy the given predicate.
     * @param Closure(Element, int=): bool|null $where A closure that takes an element of the sequence as its argument and returns a Boolean value indicating whether the element should be removed from the collection.
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
     * @return Element|null A member of the collection. If the collection is empty, it returns null.
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
     * @return Element|null The last element of the collection if the collection is not empty; otherwise, null.
     */
    #[Override]
    public function popLast()
    {
        return $this->mutableCollectionPopLast();
    }

    /**
     * Returns a subsequence by skipping elements while predicate returns true and returning the remaining elements.
     * @param Closure(Element): bool $while A closure that takes an element of the sequence as its argument and returns true if the element should be skipped or false if it should be included.
     * Once the predicate returns false, it will not be called again.
     * @return Slice<Element>
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
     * @return Slice<Element> A subsequence starting after the specified number of elements.
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
     * @return Slice<Element> A subsequence that leaves off the specified number of elements at the end.
     */
    #[Override]
    public function dropLast(int $k): Slice
    {
        return $this->mutableCollectionDropLast($k);
    }

    /**
     * Inserts the given element in the collection if it is not already present.
     *
     * If an element equal to newElement is already contained in the collection, this method has no effect.
     * @param Element $newElement An element to insert into the collection.
     * @return array{inserted: bool, elementAfterInsert: Element} (true, newElement) if `newElement` was not contained in the collection. If an element equal to `newElement` was already contained in the collection, the method returns (false, oldElement), where oldElement is the element that was equal to `newElement`. In some cases, oldElement may be distinguishable from `newElement` by identity comparison or some other means.
     */
    #[Override]
    public function insert(mixed $newElement): array
    {
        return $this->setAlgebraInsert($newElement);
    }

    /**
     * Inserts the given element into the collection unconditionally.
     *
     * If an element equal to `element` is already contained in the collection, `element` replaces the existing element.
     * @param Element $element An element to insert into the collection.
     * @return Element|null An element equal to `element` if the collection already contained such a member; otherwise, null.
     */
    #[Override]
    public function update(mixed $element)
    {
        return $this->setAlgebraUpdate($element);
    }

    /**
     * Removes the given element and any elements subsumed by the given element.
     *
     * @param Element $element
     */
    #[Override]
    public function remove(mixed $element): void
    {
        $this->setAlgebraRemove($element);
    }

    /**
     * Determines whether a given object is present in the set and returns that object if it is.
     * Each element of the set is checked for equality with an object until a match is found or the end of the set is reached. Objects are considered equal if {@see Equatable::isEqual()} returns true.
     * @param Element $element An object to look for in the set.
     * @return Element|null Returns an object equal to object if it's present in the set, otherwise null.
     */
    public function member(mixed $element)
    {
        return $this->setAlgebraMember($element);
    }

    /**
     * Returns a new set with the elements of both this and the given set.
     * @param iterable<Element> $other A sequence of elements. $other must be finite.
     * @return Set<Element> A new set with the unique elements of this set and other.
     */
    #[Override]
    public function union(iterable $other): Set
    {
        return $this->setAlgebraUnion($other);
    }

    /**
     * Inserts the elements of the given sequence into the set.
     * If the set already contains one or more elements that are also in other, the existing members are kept.
     * If $other contains multiple instances of equivalent elements, only the first instance is kept.
     * @param iterable<Element> $other A sequence of elements. $other must be finite.
     */
    #[Override]
    public function formUnion(iterable $other): void
    {
        $this->setAlgebraFormUnion($other);
    }

    /**
     * Returns a new set with the elements that are common to both this set and the given set.
     * @param SetAlgebra<Element> $other Another set.
     * @return Set<Element> A new set.
     */
    #[Override]
    public function intersection(SetAlgebra $other): Set
    {
        return $this->setAlgebraIntersection($other);
    }

    /**
     * Removes the elements of the set that aren't also in the given sequence.
     * @param SetAlgebra<Element> $other A sequence of elements. $other must be finite.
     */
    #[Override]
    public function formIntersection(SetAlgebra $other): void
    {
        $this->setAlgebraFormIntersection($other);
    }

    /**
     * Returns a new set with the elements that are either in this set or in the given sequence, but not in both.
     * @param SetAlgebra<Element> $other A sequence of elements. $other must be finite.
     * @return Set<Element> A new set.
     */
    #[Override]
    public function symmetricDifference(SetAlgebra $other): Set
    {
        return $this->setAlgebraSymmetricDifference($other);
    }

    /**
     * Removes the elements of the set that are also in the given sequence and adds the members of the sequence that are not already in the set.
     * @param SetAlgebra<Element> $other Another set.
     */
    #[Override]
    public function formSymmetricDifference(SetAlgebra $other): void
    {
        $this->setAlgebraFormSymmetricDifference($other);
    }

    /**
     * Removes the elements of the given set from this set.
     * @param SetAlgebra<Element> $other Another set.
     */
    #[Override]
    public function subtract(SetAlgebra $other): void
    {
        $this->setAlgebraSubtract($other);
    }

    /**
     * Returns a new set containing the elements of this set that do not occur in the given set.
     * @param SetAlgebra<Element> $other Another set.
     * @return Set<Element> A new set.
     */
    #[Override]
    public function subtracting(SetAlgebra $other): Set
    {
        return $this->setAlgebraSubtracting($other);
    }

    /**
     * Returns a Boolean value that indicates whether the set is a subset of another set.
     * Set A is a subset of another set B if every member of A is also a member of B.
     * @param SetAlgebra<Element> $other Another set.
     * @return bool true if the set is a subset of other; otherwise, false.
     */
    #[Override]
    public function isSubset(SetAlgebra $other): bool
    {
        return $this->setAlgebraIsSubset($other);
    }

    /**
     * Returns a Boolean value that indicates whether this set is a superset of the given set.
     * Set A is a superset of another set B if every member of B is also a member of A.
     * @param SetAlgebra<Element> $other Another set.
     * @return bool true if the set is a superset of other; otherwise, false.
     */
    #[Override]
    public function isSuperset(SetAlgebra $other): bool
    {
        return $this->setAlgebraIsSuperset($other);
    }

    /**
     * Returns a Boolean value that indicates whether the set has no members in common with the given set.
     * @param SetAlgebra<Element> $other Another set.
     * @return bool true if the set has no elements in common with other; otherwise, false.
     */
    #[Override]
    public function isDisjoint(SetAlgebra $other): bool
    {
        return $this->setAlgebraIsDisjoint($other);
    }

    /**
     * @param Set<Element> $set
     */
    public function setSet(Set $set): void
    {
        $this->reserved = $set->array;
    }

    /**
     * @return Element
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
     * @return Element
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
     * @param Element $value
     */
    #[Override]
    public function offsetSet(mixed $offset, mixed $value): void
    {
        if ($this->containsElement($value)) {
            return;
        }
        if ($offset === null) {
            $this->reserved[] = $value;
            return;
        }
        $this->reserved[$offset] = $value;
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
}
