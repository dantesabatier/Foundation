<?php

namespace Sabatier\Foundation;

use Closure;

/**
 * A collection that supports backward as well as forward traversal.
 *
 * @template Index of array-key
 * @template Element
 * @template-extends Collection<Index, Element>
 */
interface BidirectionalCollection extends Collection
{
    public mixed $last {
        get;
    }

    /**
     * Returns the last element of the collection that satisfies the given predicate.
     * @param Closure(Element, Index=): bool|null $where A closure that takes an element of the collection as its argument and returns a Boolean value indicating whether the element is a match.
     * @return Element|null The last element of the collection that satisfies predicate, or nil if there is no element that satisfies predicate.
     */
    public function last(?Closure $where = null);

    /**
     * Returns the position immediately before the given index.
     * @param int $i The index value immediately before $i.
     * @return int A valid index of the collection. $i must be greater than startIndex.
     */
    public function indexBefore(int $i): int;

    /**
     * Replaces the given index with its predecessor.
     *
     * @param int $i A valid index of the collection. $i must be greater than startIndex.
     */
    public function formIndexBefore(int &$i): void;

    /**
     * Returns a random element of the collection, using the given generator as a source for randomness.
     * @param RandomNumberGenerator $generator The random number generator to use when choosing a random element.
     * @return Element|null A random element from the collection. If the collection is empty, the method returns nil.
     */
    public function randomElement(RandomNumberGenerator $generator = new SystemRandomNumberGenerator());

    /**
     * Returns the last index in which an element of the collection satisfies the given predicate.
     * @param Closure(Element): bool $where A closure that takes an element as its argument and returns a Boolean value that indicates whether the passed element represents a match.
     * @return Index|null The index of the last element for which predicate returns true.
     * If no elements in the collection satisfy the given predicate, returns nil.
     */
    public function lastIndex(Closure $where);

    /**
     * Returns A collection containing the elements of this sequence in reverse order.
     * @return BidirectionalCollection<Index, Element> A collection containing the elements of this sequence in reverse order.
     */
    public function reversed(): BidirectionalCollection;
}
