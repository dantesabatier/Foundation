<?php

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
 * Interface MutableCollection
 * @package Sabatier\Foundation
 * @template Index of array-key
 * @template Element
 * @template-extends BidirectionalCollection<Index, Element>
 */
interface MutableCollection extends BidirectionalCollection
{
    /**
     * Adds an element to the end of the collection.
     * @param Element $element
     */
    public function append(mixed $element): void;

    /**
     * Adds the elements of a sequence or collection to the end of this collection.
     * @param iterable<Element> $newElements
     */
    public function appendContentsOf(iterable $newElements): void;

    /**
     * Removes the given element and any elements subsumed by the given element.
     * @param Element $element
     */
    public function remove(mixed $element): void;

    /**
     * Removes the specified number of elements from the beginning of the collection.
     * @param int $k The number of elements to remove. k must be greater than or equal to zero, and must be less than or equal to the number of elements in the collection.
     */
    public function removeFirst(int $k): void;

    /**
     * Removes the specified number of elements from the end of the collection.
     * Attempting to remove more elements than exist in the collection triggers a runtime error.
     * @param int $k The number of elements to remove from the collection. k must be greater than or equal to zero and must not exceed the number of elements in the collection.
     */
    public function removeLast(int $k): void;

    /**
     * Removes all the elements that satisfy the given predicate.
     * @param Closure(Element, Index=): bool|null $where A closure that takes an element of the sequence as its argument and returns a Boolean value indicating whether the element should be removed from the collection.
     */
    public function removeAll(Closure $where = null): void;

    /**
     * Removes and returns the first element of the collection.
     * @return Element|null A member of the collection. If the collection is empty, returns nil.
     */
    public function popFirst();

    /**
     * Returns a subsequence by skipping elements while predicate returns true and returning the remaining elements.
     * @param Closure(Element): bool $while A closure that takes an element of the sequence as its argument and returns true if the element should be skipped or false if it should be included.
     * Once the predicate returns false it will not be called again.
     * @return mixed
     */
    public function drop(Closure $while): mixed;

    /**
     * Returns a subsequence containing all but the given number of initial elements.
     * If the number of elements to drop exceeds the number of elements in the collection, the result is an empty subsequence.
     * @param int $k The number of elements to drop from the beginning of the collection. k must be greater than or equal to zero.
     * @return mixed A subsequence starting after the specified number of elements.
     */
    public function dropFirst(int $k): mixed;

    /**
     * Returns a subsequence containing all but the specified number of final elements.
     * If the number of elements to drop exceeds the number of elements in the collection, the result is an empty subsequence.
     * @param int $k The number of elements to drop off the end of the collection. k must be greater than or equal to zero.
     * @return mixed A subsequence that leaves off the specified number of elements at the end.
     */
    public function dropLast(int $k): mixed;
}
