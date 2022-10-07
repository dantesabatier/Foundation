<?php

/**
 * Created by PhpStorm.
 * User: dante
 * Date: 17/06/20
 * Time: 07:11
 */

namespace Sabatier\Foundation;

use Closure;
use Iterator;
use JetBrains\PhpStorm\Pure;

/**
 * Class Set
 * An unordered collection of unique elements.
 * @package Sabatier\Foundation
 * @template Element
 * @implements Iterator<int, Element>
 * @implements SetAlgebra<Element>
 */
class Set extends ObjectClass implements SetAlgebra, Iterator
{
    use MutableCollectionAlgorithms {
        contains as protected sequenceContains;
        containsElement as protected sequenceContainsElement;
        first as protected sequenceFirst;
        last as protected sequenceLast;
        min as protected sequenceMin;
        max as protected sequenceMax;
        reduce as protected sequenceReduce;
        map as protected sequenceMap;
        compactMap as protected sequenceCompactMap;
        flatMap as protected sequenceFlatMap;
        valueForKey as protected collectionValueForKey;
        setValueForKey as protected collectionSetValueForKey;
        valueForKeyPath as protected collectionValueForKeyPath;
        randomElement as protected collectionRandomElement;
        firstIndex as protected collectionFirstIndex;
        lastIndex as protected collectionLastIndex;
        indexOf as protected collectionIndexOf;
        filter as protected collectionFilter;
        filtered as protected collectionFiltered;
        sorted as protected collectionSorted;
        allSatisfy as protected collectionAllSatisfy;
        joined as protected collectionJoined;
        drop as protected mutableCollectionDrop;
        dropFirst as protected mutableCollectionDropFirst;
        dropLast as protected mutableCollectionDropLast;
    }

    /** @var Element[] */
    private array $reserved = [];
    private int $position = 0;

    /**
     * @param iterable<Element> $iterable
     */
    public function __construct(iterable $iterable = [])
    {
        if ($iterable instanceof Set) {
            $this->reserved = $iterable->toArray();
        } else {
            $this->appendContentsOf($iterable);
        }
    }

    public function __clone()
    {
        $this->reserved = $this->toArray();
    }

    public function __serialize(): array
    {
        return $this->reserved;
    }

    public function __unserialize(array $data): void
    {
        $this->reserved = $data;
    }

    public function __isset(string $name): bool
    {
        return isset($this->reserved[$name]);
    }

    public function __unset(string $name): void
    {
        unset($this->reserved[$name]);
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
     * Available when Element conforms to {@see Equatable}.
     * @param Element $element The element to find in the sequence.
     * @return bool {@see true} if the element was found in the sequence; otherwise, {@see false}.
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
     * @param Closure(Element, int=): Result $transform
     * @return Set<Result>
     */
    public function map(Closure $transform): Set
    {
        return $this->sequenceMap($transform);
    }

    /**
     * @template Result
     * Returns a Collection containing the non-nil results of calling the given transformation with each element of this collection.
     * @param Closure(Element, int=): Result $transform
     * @return Set<Result>
     */
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
    public function flatMap(Closure $transform): Set
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
     * @param int $index
     * @return Element|null
     */
    public function elementAt(mixed $index)
    {
        return $this->reserved[$index] ?? null;
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
     * @return Set<Element>
     */
    public function filter(Closure $isIncluded): Set
    {
        return $this->collectionFilter($isIncluded);
    }

    /**
     * Evaluates a given predicate against each object in the receiving and returns a new collection containing the objects for which the predicate returns true.
     * @param Predicate $predicate The predicate against which to evaluate the receiving collection's elements.
     * @return Set<Element> A new collection containing the objects in the receiving array for which predicate returns true.
     * Objects in the resulting array appear in the same order as they do in the receiver.
     */
    public function filtered(Predicate $predicate): Set
    {
        return $this->collectionFiltered($predicate);
    }

    /**
     * Sorts the collection in place.
     * @param Closure(Element, Element): int $by
     * @return Set<Element>
     */
    public function sort(Closure $by): Set
    {
        usort($this->reserved, $by);
        return $this;
    }

    /**
     * Returns a copy of the receiving sequence sorted as specified by a given array of sort descriptors.
     * @param iterable<SortDescriptor> $descriptors A sequence of {@see SortDescriptor} objects.
     * @return Set<Element> A copy of the receiving sequence sorted as specified by descriptors.
     */
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
        return (new ArrayClass($this))->shuffled($generator);
    }

    /**
     * Returns a Boolean value indicating whether every element of a sequence satisfies a given predicate.
     * @param Closure(Element, int=): bool $predicate A closure that takes an element of the sequence as its argument and returns a Boolean value that indicates whether the passed element satisfies a condition.
     * @return bool {@see true} if the sequence contains an element that satisfies predicate; otherwise, {@see false}.
     */
    public function allSatisfy(Closure $predicate): bool
    {
        return $this->collectionAllSatisfy($predicate);
    }

    /**
     * Returns the elements of this sequence of sequences, concatenated.
     * @return FlattenSequence<Set<Element>> A flattened view of the elements of this sequence of sequences.
     * @psalm-suppress LessSpecificReturnStatement, MoreSpecificReturnType
     */
    public function joined(): FlattenSequence
    {
        return $this->collectionJoined();
    }

    /**
     * Returns a subsequence by skipping elements while predicate returns true and returning the remaining elements.
     * @param Closure(Element): bool $while A closure that takes an element of the sequence as its argument and returns true if the element should be skipped or false if it should be included.
     * Once the predicate returns false it will not be called again.
     * @return Slice<Set<Element>>
     * @psalm-suppress LessSpecificReturnStatement, MoreSpecificReturnType
     */
    public function drop(Closure $while): Slice
    {
        return $this->mutableCollectionDrop($while);
    }

    /**
     * Returns a subsequence containing all but the given number of initial elements.
     * If the number of elements to drop exceeds the number of elements in the collection, the result is an empty subsequence.
     * @param int $k The number of elements to drop from the beginning of the collection. k must be greater than or equal to zero.
     * @return Slice<Set<Element>> A subsequence starting after the specified number of elements.
     * @psalm-suppress LessSpecificReturnStatement, MoreSpecificReturnType
     */
    public function dropFirst(int $k): Slice
    {
        return $this->mutableCollectionDropFirst($k);
    }

    /**
     * Returns a subsequence containing all but the specified number of final elements.
     * If the number of elements to drop exceeds the number of elements in the collection, the result is an empty subsequence.
     * @param int $k The number of elements to drop off the end of the collection. k must be greater than or equal to zero.
     * @return Slice<Set<Element>> A subsequence that leaves off the specified number of elements at the end.
     * @psalm-suppress LessSpecificReturnStatement, MoreSpecificReturnType
     */
    public function dropLast(int $k): Slice
    {
        return $this->mutableCollectionDropLast($k);
    }

    /**
     * Adds an element to the end of the collection.
     * @param Element $element
     */
    public function append(mixed $element): void
    {
        if (!$this->containsElement($element)) {
            $this->reserved[] = $element;
        }
    }

    /**
     * Adds the elements of a sequence or collection to the end of this collection.
     * @param iterable<Element> $newElements
     */
    public function appendContentsOf(iterable $newElements): void
    {
        foreach ($newElements as $element) {
            $this->append($element);
        }
    }

    /**
     * Inserts the given element in the set if it is not already present.
     * If an element equal to newMember is already contained in the set, this method has no effect.
     * @param Element $newMember An element to insert into the set.
     * @return array{inserted: boolean, memberAfterInsert: Element} (true, newMember) if newMember was not contained in the set. If an element equal to newMember was already contained in the set, the method returns (false, oldMember), where oldMember is the element that was equal to newMember. In some cases, oldMember may be distinguishable from newMember by identity comparison or some other means.
     * If an element equal to newMember is already contained in the set, this method has no effect.
     */
    public function insert(mixed $newMember): array
    {
        $oldMember = $this->member($newMember);
        if ($oldMember === null) {
            $this->append($newMember);
            return ['inserted' => true, 'memberAfterInsert' => $newMember];
        }
        return ['inserted' => false, 'memberAfterInsert' => $oldMember];
    }

    /**
     * Inserts the given element into the set unconditionally.
     * If an element equal to newMember is already contained in the set, newMember replaces the existing element.
     * @param Element $element An element to insert into the set.
     * @return Element|null An element equal to newMember if the set already contained such a member; otherwise, nil.
     */
    public function update(mixed $element)
    {
        $index = $this->indexOf($element);
        if ($index !== null) {
            $member = $this->offsetGet($index);
            $this->reserved[$index] = $element;
            return $member;
        }
        $this->append($element);
        return null;
    }

    /**
     * Removes the given element and any elements subsumed by the given element.
     * @param Element $element
     */
    public function remove(mixed $element): void
    {
        $index = $this->indexOf($element);
        if ($index !== null) {
            $this->removeAt($index);
        }
    }

    /**
     * Removes the element at the given index of the set.
     * @return Element The element that was removed from the set.
     */
    public function removeAt(int $index)
    {
        $member = $this->offsetGet($index);
        $this->offsetUnset($index);
        $this->reserved = array_values($this->reserved);
        return $member;
    }

    /**
     * Removes all the elements that satisfy the given predicate.
     * @param Closure(Element, int=): bool|null $where A closure that takes an element of the sequence as its argument and returns a Boolean value indicating whether the element should be removed from the collection.
     */
    public function removeAll(Closure $where = null): void
    {
        if ($where === null) {
            $this->reserved = [];
            return;
        }
        $this->reserved = $this->filter(fn(mixed $e, int $i): bool => !$where($e, $i))->reserved;
    }

    public function removeFirst(int $k): void
    {
        $this->removeAll(fn(mixed $e, int $i): bool => $i < $k);
    }

    public function removeLast(int $k): void
    {
        if ($k === 0) {
            return;
        }
        assert($k > 0, "Number of elements to remove should be non-negative");
        $e = $this->endIndex();
        $i = $e - $k;
        assert($i >= 0 && $i < $e, "Can't remove more items from a collection than it contains");
        while ($i < $e) {
            $this->offsetUnset($i);
            $this->formIndexAfter($i);
        }
        $this->reserved = array_values($this->reserved);
    }

    /**
     * Removes and returns the first element of the collection.
     * @return Element|null A member of the collection. If the collection is empty, returns nil.
     */
    public function popFirst()
    {
        if ($this->isEmpty()) {
            return null;
        }
        return $this->removeAt(0);
    }

    /**
     * Determines whether a given object is present in the set, and returns that object if it is.
     * Each element of the set is checked for equality with object until a match is found or the end of the set is reached. Objects are considered equal if {@see Equatable::isEqual()} returns true.
     * @param Element $element An object to look for in the set.
     * @return Element|null Returns an object equal to object if it's present in the set, otherwise nil.
     */
    public function member(mixed $element)
    {
        return $this->first(fn(mixed $e): bool => $e instanceof Equatable ? $e->isEqual($element) : $e === $element);
    }

    /**
     * Returns a new set with the elements of both this and the given set.
     * @param SetAlgebra<Element> $other A sequence of elements. other must be finite.
     * @return Set<Element> A new set with the unique elements of this set and other.
     */
    public function union(SetAlgebra $other): Set
    {
        $copy = clone $this;
        $copy->formUnion($other);
        return $copy;
    }

    /**
     * Inserts the elements of the given sequence into the set.
     * If the set already contains one or more elements that are also in other, the existing members are kept.
     * If other contains multiple instances of equivalent elements, only the first instance is kept.
     * @param SetAlgebra<Element> $other A sequence of elements. other must be finite.
     */
    public function formUnion(SetAlgebra $other): void
    {
        $this->appendContentsOf($other);
    }

    /**
     * Returns a new set with the elements that are common to both this set and the given set.
     * @param SetAlgebra<Element> $other Another set.
     * @return Set<Element> A new set.
     */
    public function intersection(SetAlgebra $other): Set
    {
        $copy = clone $this;
        $copy->formIntersection($other);
        return $copy;
    }

    /**
     * Removes the elements of the set that aren't also in the given sequence.
     * @param SetAlgebra<Element> $other A sequence of elements. other must be finite.
     */
    public function formIntersection(SetAlgebra $other): void
    {
        foreach ($this as $member) {
            if (!$other->containsElement($member)) {
                $this->remove($member);
            }
        }
    }

    /**
     * Returns a new set with the elements that are either in this set or in the given sequence, but not in both.
     * @param SetAlgebra<Element> $other A sequence of elements. other must be finite.
     * @return Set<Element> A new set.
     */
    public function symmetricDifference(SetAlgebra $other): Set
    {
        $copy = clone $this;
        $copy->formSymmetricDifference($other);
        return $copy;
    }

    /**
     * Removes the elements of the set that are also in the given sequence and adds the members of the sequence that are not already in the set.
     * @param SetAlgebra<Element> $other Another set.
     */
    public function formSymmetricDifference(SetAlgebra $other): void
    {
        foreach ($other as $member) {
            if ($this->containsElement($member)) {
                $this->remove($member);
            } else {
                $this->append($member);
            }
        }
    }

    /**
     * Removes the elements of the given set from this set.
     * @param SetAlgebra<Element> $other Another set.
     */
    public function subtract(SetAlgebra $other): void
    {
        foreach ($other as $member) {
            $this->remove($member);
        }
    }

    /**
     * Returns a new set containing the elements of this set that do not occur in the given set.
     * @param SetAlgebra<Element> $other Another set.
     * @return Set<Element> A new set.
     */
    public function subtracting(SetAlgebra $other): Set
    {
        $copy = clone $this;
        $copy->subtract($other);
        return $copy;
    }

    /**
     * Returns a Boolean value that indicates whether the set is a subset of another set.
     * Set A is a subset of another set B if every member of A is also a member of B.
     * @param SetAlgebra<Element> $other Another set.
     * @return bool {@see true} if the set is a subset of other; otherwise, {@see false}.
     */
    public function isSubset(SetAlgebra $other): bool
    {
        if ($this->compare($other) != ComparisonResult::orderedAscending) {
            return false;
        }
        foreach ($this as $element) {
            if (!$other->containsElement($element)) {
                return false;
            }
        }
        return true;
    }

    /**
     * Returns a Boolean value that indicates whether this set is a superset of the given set.
     * Set A is a superset of another set B if every member of B is also a member of A.
     * @param SetAlgebra<Element> $other Another set.
     * @return bool {@see true} if the set is a superset of other; otherwise, {@see false}.
     */
    public function isSuperset(SetAlgebra $other): bool
    {
        if ($this->compare($other) != ComparisonResult::orderedDescending) {
            return false;
        }
        foreach ($this as $element) {
            if (!$other->containsElement($element)) {
                return false;
            }
        }
        return true;
    }

    /**
     * Returns a Boolean value that indicates whether the set has no members in common with the given set.
     * @param SetAlgebra<Element> $other Another set.
     * @return bool {@see true} if the set has no elements in common with other; otherwise, {@see false}.
     */
    public function isDisjoint(SetAlgebra $other): bool
    {
        return !$this->contains(fn(mixed $member): bool => $other->containsElement($member));
    }

    /**
     * Reverses the elements of the collection in place.
     * @return Set<Element>
     */
    public function reverse(): Set
    {
        $this->reserved = array_reverse($this->reserved);
        return $this;
    }

    /**
     * Returns a collection containing the elements of this sequence in reverse order.
     * @return Set<Element> A collection containing the elements of this sequence in reverse order.
     */
    public function reversed(): Set
    {
        $instance = clone $this;
        $instance->reverse();
        return $instance;
    }

    public function setSet(Set $set): void
    {
        $this->reserved = $set->reserved;
    }

    /**
     * @return Element[]
     */
    public function toArray(): array
    {
        return $this->reserved;
    }

    /**
     * Return a set containing the results of invoking {@see KeyValueCoding::valueForKey()} on each of the receiving set's members.
     * The returned set might not have the same number of members as the receiving set. The returned set will not contain any elements corresponding to instances of valueForKey() returning nil.
     * @param string $key The name of one of the properties of the receiving set's members.
     * @return Set A set containing the results of invoking {@see KeyValueCoding::valueForKey()} (with the argument key) on each of the receiving set's members.
     */
    public function valueForKey(string $key): Set
    {
        return $this->collectionValueForKey($key);
    }

    /**
     * Invokes {@see KeyValueCoding::setValueForKey()} on each of the set's members.
     * @param mixed $value The value for the property identified by key.
     * @param string $key The name of one of the properties of the set's members.
     */
    public function setValueForKey(mixed $value, string $key): void
    {
        $this->collectionSetValueForKey($value, $key);
    }

    public function description(): string
    {
        return "[" . $this->join(', ') . "]";
    }

    /**
     * @return Element
     */
    public function current(): mixed
    {
        return $this->offsetGet($this->key());
    }

    public function next(): void
    {
        $this->formIndexAfter($this->position);
    }

    #[Pure]
    public function key(): int
    {
        return $this->position;
    }

    #[Pure]
    public function valid(): bool
    {
        return $this->offsetExists($this->key());
    }

    public function rewind(): void
    {
        $this->position = 0;
    }

    public function count(): int
    {
        return count($this->reserved);
    }

    /**
     * @param int $offset
     */
    #[Pure]
    public function offsetExists(mixed $offset): bool
    {
        return array_key_exists($offset, $this->reserved);
    }

    /**
     * @param int $offset
     * @return Element
     */
    public function offsetGet(mixed $offset): mixed
    {
        assert(in_range($offset, $this->startIndex(), $this->endIndex()), sprintf("%s %s(%s) index \"%s\" out of bounds [%s...<%s]", $this->debugDescription(), __FUNCTION__, $offset, $offset, $this->startIndex(), $this->endIndex()));
        return $this->reserved[$offset];
    }

    /**
     * @param int|null $offset
     * @param Element $value
     */
    public function offsetSet(mixed $offset, mixed $value): void
    {
        if (!$this->containsElement($value)) {
            if ($offset === null) {
                $this->reserved[] = $value;
            } else {
                $this->reserved[$offset] = $value;
            }
        }
    }

    /**
     * @param int $offset
     */
    public function offsetUnset(mixed $offset): void
    {
        if ($this->offsetExists($offset)) {
            unset($this->reserved[$offset]);
        }
    }
}
