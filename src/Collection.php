<?php

/**
 * Created by PhpStorm.
 * User: dante
 * Date: 17/07/20
 * Time: 13:21
 */

namespace Sabatier\Foundation;

use ArrayAccess;
use Closure;
use Sabatier\Foundation\Predicates\Predicate;

/**
 * A collection whose elements can be traversed multiple times, nondestructively, and accessed by an indexed subscript.
 * @template Index of array-key
 * @template Element
 * @template-extends Sequence<Index, Element>
 * @template-extends ArrayAccess<Index, Element>
 */
interface Collection extends Sequence, ArrayAccess
{
    /**
     * The position of the first element in a nonempty collection.
     */
    public function startIndex(): int;

    /**
     * The collection's “past the end” position—that is, the position one greater than the last valid subscript argument.
     */
    public function endIndex(): int;

    /**
     * The indices that are valid for subscripting the collection, in ascending order.
     */
    public function indices(): Range;

    /**
     * Returns the position immediately after the given index.
     * @param int $i A valid index of the collection. $i must be less than endIndex.
     * @return int The index value immediately after $i.
     */
    public function indexAfter(int $i): int;

    /**
     * Replaces the given index with its successor.
     * @param int $i A valid index of the collection. $i must be less than endIndex.
     */
    public function formIndexAfter(int &$i): void;

    /**
     * Returns the first index in which an element of the collection satisfies the given predicate.
     * @param Closure(Element): bool $where A closure that takes an element as its argument and returns a Boolean value that indicates whether the passed element represents a match.
     * @return Index|null The index of the first element for which predicate returns true.
     * If no elements in the collection satisfy the given predicate, returns nil.
     */
    public function firstIndex(Closure $where);

    /**
     * Returns the first index where the specified value appears in the collection.
     * @param Element $element An element to search for in the collection.
     * @return Index|null The first index where element is found. If element is not found in the collection, returns nil.
     */
    public function indexOf(mixed $element);

    /**
     * @param Index $index
     * @return Element|null
     */
    public function elementAt(mixed $index);

    /**
     * Returns the distance between two indices.
     * 
     * @param int $start A valid index of the collection.
     * @param int $end Another valid index of the collection. If end is equal to start, the result is zero.
     * @return int The distance between start and end. The result can be negative only if the collection conforms to the BidirectionalCollection protocol.
     */
    public function distance(int $start, int $end): int;

    /**
     * A Boolean value indicating whether the sequence is empty.
     */
    public function isEmpty(): bool;

    /**
     * Returns a Collection containing, in order, the elements of the collection that satisfy the given predicate.
     * @param Closure(Element, Index=, bool=): bool $isIncluded
     * @return Collection<Index, Element>
     */
    public function filter(Closure $isIncluded): Collection;

    /**
     * Evaluates a given predicate against each object in the receiving and returns a new collection containing the objects for which the predicate returns true.
     * @param Predicate $predicate The predicate against which to evaluate the receiving collection's elements.
     * @return Collection<Index, Element> A new collection containing the objects in the receiving array for which predicate returns true.
     * Objects in the resulting array appear in the same order as they do in the receiver.
     */
    public function filtered(Predicate $predicate): Collection;

    /**
     * Sorts the collection in place.
     * @param Closure(Element, Element): int $by
     * @return Collection<Index, Element>
     */
    public function sort(Closure $by): Collection;

    /**
     * Returns a copy of the receiving sequence sorted as specified by a given array of sort descriptors.
     * @param iterable<SortDescriptor> $descriptors A sequence of {@see SortDescriptor} objects.
     * @return Collection<Index, Element> A copy of the receiving sequence sorted as specified by descriptors.
     */
    public function sorted(iterable $descriptors): Collection;

    /**
     * Returns a Boolean value indicating whether every element of a sequence satisfies a given predicate.
     * @param Closure(Element, Index=): bool $predicate A closure that takes an element of the sequence as its argument and returns a Boolean value that indicates whether the passed element satisfies a condition.
     * @return bool {@see true} if the sequence contains an element that satisfies predicate; otherwise, {@see false}.
     */
    public function allSatisfy(Closure $predicate): bool;

    /**
     * Returns a new string by concatenating the elements of the sequence, adding the given separator between each element.
     * @param string $separator A string to insert between each of the elements in this sequence. The default separator is an empty string.
     * @return string A single, concatenated string.
     */
    public function join(string $separator): string;

    /**
     * Returns the elements of this sequence of sequences, concatenated.
     * @return FlattenSequence A flattened view of the elements of this sequence of sequences.
     */
    public function joined(): FlattenSequence;
}
