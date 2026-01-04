<?php

/**
 * Created by PhpStorm.
 * User: dante
 * Date: 17/07/20
 * Time: 14:23
 */

namespace Sabatier\Foundation;

/**
 * A type that provides mathematical set operations.
 * @template Element
 * @template-extends MutableCollection<int, Element>
 */
interface SetAlgebra extends MutableCollection
{
    /**
     * Inserts the given element in the collection if it is not already present.
     *
     * If an element equal to newElement is already contained in the collection, this method has no effect.
     * @param Element $newElement An element to insert into the collection.
     * @return array{inserted: bool, elementAfterInsert: Element} (true, newElement) if newElement was not contained in the collection. If an element equal to newElement was already contained in the collection, the method returns (false, oldElement), where oldElement is the element that was equal to newElement. In some cases, oldElement may be distinguishable from newElement by identity comparison or some other means.
     */
    public function insert(mixed $newElement): array;

    /**
     * Inserts the given element into the collection unconditionally.
     *
     * If an element equal to newElement is already contained in the collection, newElement replaces the existing element.
     * @param Element $element An element to insert into the collection.
     * @return Element|null An element equal to newElement if the collection already contained such a member; otherwise, null.
     */
    public function update(mixed $element);

    /**
     * Returns a new set with the elements of both this and the given set.
     *
     * Complexity: O(n + m), where n is the length of this set and m is the length of `other`.
     *
     * @param iterable<Element> $other A sequence of elements. `other` must be finite.
     * @return SetAlgebra<Element> A new set with the unique elements of this set and `other`.
     */
    public function union(iterable $other): SetAlgebra;

    /**
     * Inserts the elements of the given sequence into the set.
     *
     * Complexity: O(n + m), where n is the length of this set and m is the length of `other`.
     *
     * If the set already contains one or more elements that are also in `other`, the existing members are kept.
     * If `other` contains multiple instances of equivalent elements, only the first instance is kept.
     * @param iterable<Element> $other A sequence of elements. `other` must be finite.
     */
    public function formUnion(iterable $other): void;

    /**
     * Returns a new set with the elements that are common to both this set and the given set.
     * @param SetAlgebra<Element> $other Another set.
     * @return SetAlgebra<Element> A new set.
     */
    public function intersection(SetAlgebra $other): SetAlgebra;

    /**
     * Removes the elements of the set that aren't also in the given sequence.
     *
     * Complexity: O(n + m), where n is the length of this set and m is the length of `other`.
     *
     * @param SetAlgebra<Element> $other A sequence of elements. `other` must be finite.
     */
    public function formIntersection(SetAlgebra $other): void;

    /**
     * Returns a new set with the elements that are either in this set or in the given sequence, but not in both.
     *
     * Complexity: O(n + m), where n is the length of this set and m is the length of `other`.
     *
     * @param SetAlgebra<Element> $other A sequence of elements. `other` must be finite.
     * @return SetAlgebra<Element> A new set.
     */
    public function symmetricDifference(SetAlgebra $other): SetAlgebra;

    /**
     * Removes the elements of the set that are also in the given sequence and adds the members of the sequence that are not already in the set.
     *
     * Complexity: O(n + m), where n is the length of this set and m is the length of `other`.
     *
     * @param SetAlgebra<Element> $other Another set.
     */
    public function formSymmetricDifference(SetAlgebra $other): void;

    /**
     * Removes the elements of the given set from this set.
     *
     * Complexity: O(n + m), where n is the length of this set and m is the length of `other`.
     *
     * @param SetAlgebra<Element> $other Another set.
     */
    public function subtract(SetAlgebra $other): void;

    /**
     * Returns a new set containing the elements of this set that do not occur in the given set.
     *
     * Complexity: O(n + m), where n is the length of this set and m is the length of `other`.
     *
     * @param SetAlgebra<Element> $other Another set.
     * @return SetAlgebra<Element> A new set.
     */
    public function subtracting(SetAlgebra $other): SetAlgebra;

    /**
     * Returns a Boolean value that indicates whether the set is a subset of another set.
     *
     * Complexity: O(n), where n is the length of this set.
     *
     * Set A is a subset of another set B if every member of A is also a member of B.
     * @param SetAlgebra<Element> $other Another set.
     * @return bool true if the set is a subset of `other`; otherwise, false.
     */
    public function isSubset(SetAlgebra $other): bool;

    /**
     * Returns a Boolean value that indicates whether this set is a superset of the given set.
     *
     * Complexity: O(n), where n is the length of `other`.
     *
     * Set A is a superset of another set B if every member of B is also a member of A.
     * @param SetAlgebra $other Another set.
     * @return bool true if the set is a superset of `other`; otherwise, false.
     */
    public function isSuperset(SetAlgebra $other): bool;

    /**
     * Returns a Boolean value that indicates whether the set has no members in common with the given set.
     *
     * Complexity: O(n*m), where n is the length of this set and m is the length of `other`.
     *
     * @param SetAlgebra<Element> $other Another set.
     * @return bool true if the set has no elements in common with `other`; otherwise, false.
     */
    public function isDisjoint(SetAlgebra $other): bool;
}
