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

/**
 * A collection whose elements can be traversed multiple times, nondestructively, and accessed by an indexed subscript.
 * @template Index of array-key
 * @template Element
 * @template-extends Sequence<Index, Element>
 * @template-extends ArrayAccess<Index, Element>
 */
interface Collection extends Sequence, ArrayAccess
{
    /** @var int The position of the first element in a nonempty collection. */
    public int $startIndex {
        get;
    }
    /** @var int The collection's "past the end" position—that is, the position one greater than the last valid subscript argument. */
    public int $endIndex {
        get;
    }
    /** @var Range The indices that are valid for subscripting the collection in ascending order. */
    public Range $indices {
        get;
    }

    /**
     * Returns the position immediately after the given index.
     * @param int $i A valid index of the collection. The $i must be less than {@see $endIndex}.
     * @return int The index value immediately after $i.
     */
    public function indexAfter(int $i): int;

    /**
     * Replaces the given index with its successor.
     * @param int $i A valid index of the collection. The $i must be less than {@see $endIndex}.
     */
    public function formIndexAfter(int &$i): void;

    /**
     * Returns the first index in which an element of the collection satisfies the given predicate.
     *
     * Complexity O(n), where n is the length of the collection.
     * @param Closure(Element): bool $where A closure that takes an element as its argument and returns a Boolean value that indicates whether the passed element represents a match.
     * @return Index|null The index of the first element for which $where returns true.
     * If no elements in the collection satisfy the given $where returns null.
     */
    public function firstIndex(Closure $where);

    /**
     * Returns the first index where the specified value appears in the collection.
     * @param Element $element An element to search for in the collection.
     * @return Index|null The first index where $element is found. If the element is not found in the collection, it returns null.
     */
    public function indexOf(mixed $element);

    /**
     * Returns the distance between two indices.
     *
     * @param int $start A valid index of the collection.
     * @param int $end Another valid index of the collection. If the end is equal to $start, the result is zero.
     * @return int The distance between start and end. The result can be negative only if the collection conforms to the BidirectionalCollection protocol.
     */
    public function distance(int $start, int $end): int;

    /**
     * Sorts the collection in place.
     * @param Closure(Element, Element): int|null $by
     * @return Collection<Index, Element>
     */
    public function sort(?Closure $by = null): Collection;

    /**
     * Returns a copy of the receiving sequence sorted as specified by a given array of sort descriptors.
     * @param iterable<SortDescriptor> $descriptors A sequence of {@see SortDescriptor} objects.
     * @return Collection<Index, Element> A copy of the receiving sequence sorted as specified by descriptors.
     */
    public function sorted(iterable $descriptors): Collection;

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
