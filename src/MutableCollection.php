<?php

declare(strict_types=1);

/**
 * Created by PhpStorm.
 * User: dante
 * Date: 17/07/20
 * Time: 13:51
 */

namespace Sabatier\Foundation;

use Closure;

/**
 * A collection that supports subscript assignment.
 * @template Index of array-key
 * @template Element
 * @template-extends BidirectionalCollection<Index, Element>
 */
interface MutableCollection extends BidirectionalCollection
{
    /**
     * Inserts the value into the collection at the specified position.
     *
     * The new element is inserted before the element currently at the specified index.
     * If you pass the collection's endIndex property as the index parameter, the new element is appended to the collection.
     *
     * Complexity: O(n), where n is the length of the array. If i == endIndex, this method is equivalent to append(_:).
     * @param Element $element The new element to insert into the collection.
     * @param int $at The position at which to insert the new element. `$index` must be a valid index into the collection.
     */
    public function insertAt(mixed $element, int $at): void;

    /**
     * Removes the given element and any elements subsumed by the given element.
     * @param Element $element
     */
    public function remove(mixed $element): void;

    /**
     * Complexity: O(n), where n is the length of the array.
     * @param int $index The index of the member to remove, position must be a valid index of the collection and must not be equal to the collection's end index.
     * @return Element The value that was removed.
     */
    public function removeAt(int $index);

    /**
     * Removes the specified number of elements from the beginning of the collection.
     *
     * Complexity: O(n), where n is the length of the collection.
     * @param int $k The number of elements to remove. `k` must be greater than or equal to zero and must be less than or equal to the number of elements in the collection.
     */
    public function removeFirst(int $k): void;

    /**
     * Removes the specified number of elements from the end of the collection.
     *
     * Attempting to remove more elements than exist in the collection triggers a runtime error.
     *
     * Complexity: O(n), where n is the length of the collection.
     * @param int $k The number of elements to remove from the collection. `k` must be greater than or equal to zero and must not exceed the number of elements in the collection.
     */
    public function removeLast(int $k): void;

    /**
     * Removes all the elements that satisfy the given predicate.
     *
     * Complexity: O(n), where n is the length of the collection.
     * @param Closure(Element, Index=): bool|null $where A closure that takes an element of the sequence as its argument and returns a Boolean value indicating whether the element should be removed from the collection.
     */
    public function removeAll(?Closure $where = null): void;

    /**
     * Removes and returns the first element of the collection.
     *
     * Complexity: O(1)
     * @return Element|null A member of the collection. If the collection is empty, it returns null.
     */
    public function popFirst();

    /**
     * Removes and returns the last element of the collection.
     *
     * Complexity: O(1)
     * @return Element|null A member of the collection. If the collection is empty, it returns null.
     */
    public function popLast();

    /**
     * Returns a subsequence by skipping elements while predicate returns true and returning the remaining elements.
     *
     * Complexity: O(n), where n is the length of the collection.
     * @param Closure(Element): bool $while A closure that takes an element of the sequence as its argument and returns true if the element should be skipped or false if it should be included.
     * Once the predicate returns false it will not be called again.
     * @return Slice<Element>
     */
    public function drop(Closure $while): Slice;

    /**
     * Returns a subsequence containing all but the given number of initial elements.
     *
     * If the number of elements to drop exceeds the number of elements in the collection, the result is an empty subsequence.
     *
     * Complexity: O(1) if the collection conforms to RandomAccessCollection; otherwise, O(k), where k is the number of elements to drop from the beginning of the collection.
     * @param int $k The number of elements to drop from the beginning of the collection. `k` must be greater than or equal to zero.
     * @return Slice<Element> A subsequence starting after the specified number of elements.
     */
    public function dropFirst(int $k): Slice;

    /**
     * Returns a subsequence containing all but the specified number of final elements.
     *
     * If the number of elements to drop exceeds the number of elements in the collection, the result is an empty subsequence.
     *
     * Complexity: O(1) if the collection conforms to RandomAccessCollection; otherwise, O(k), where k is the number of elements to drop.
     * @param int $k The number of elements to drop off the end of the collection. `k` must be greater than or equal to zero.
     * @return Slice<Element> A subsequence that leaves off the specified number of elements at the end.
     */
    public function dropLast(int $k): Slice;
}
