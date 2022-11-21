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
     * Returns a new set with the elements of both this and the given set.
     * @param SetAlgebra<Element> $other A sequence of elements. other must be finite.
     * @return SetAlgebra<Element> A new set with the unique elements of this set and other.
     */
    public function union(SetAlgebra $other): SetAlgebra;

    /**
     * Inserts the elements of the given sequence into the set.
     *
     * If the set already contains one or more elements that are also in other, the existing members are kept.
     * If other contains multiple instances of equivalent elements, only the first instance is kept.
     * @param SetAlgebra<Element> $other A sequence of elements. other must be finite.
     */
    public function formUnion(SetAlgebra $other): void;

    /**
     * Returns a new set with the elements that are common to both this set and the given set.
     * @param SetAlgebra<Element> $other Another set.
     * @return SetAlgebra<Element> A new set.
     */
    public function intersection(SetAlgebra $other): SetAlgebra;

    /**
     * Removes the elements of the set that aren't also in the given sequence.
     * @param SetAlgebra<Element> $other A sequence of elements. other must be finite.
     */
    public function formIntersection(SetAlgebra $other): void;

    /**
     * Returns a new set with the elements that are either in this set or in the given sequence, but not in both.
     * @param SetAlgebra<Element> $other A sequence of elements. other must be finite.
     * @return SetAlgebra<Element> A new set.
     */
    public function symmetricDifference(SetAlgebra $other): SetAlgebra;

    /**
     * Removes the elements of the set that are also in the given sequence and adds the members of the sequence that are not already in the set.
     * @param SetAlgebra<Element> $other Another set.
     */
    public function formSymmetricDifference(SetAlgebra $other): void;

    /**
     * Removes the elements of the given set from this set.
     * @param SetAlgebra<Element> $other Another set.
     */
    public function subtract(SetAlgebra $other): void;

    /**
     * Returns a new set containing the elements of this set that do not occur in the given set.
     * @param SetAlgebra<Element> $other Another set.
     * @return SetAlgebra<Element> A new set.
     */
    public function subtracting(SetAlgebra $other): SetAlgebra;

    /**
     * Returns a Boolean value that indicates whether the set is a subset of another set.
     *
     * Set A is a subset of another set B if every member of A is also a member of B.
     * @param SetAlgebra<Element> $other Another set.
     * @return bool {@see true} if the set is a subset of other; otherwise, {@see false}.
     */
    public function isSubset(SetAlgebra $other): bool;

    /**
     * Returns a Boolean value that indicates whether this set is a superset of the given set.
     *
     * Set A is a superset of another set B if every member of B is also a member of A.
     * @param SetAlgebra $other Another set.
     * @return bool {@see true} if the set is a superset of other; otherwise, {@see false}.
     */
    public function isSuperset(SetAlgebra $other): bool;

    /**
     * Returns a Boolean value that indicates whether the set has no members in common with the given set.
     * @param SetAlgebra<Element> $other Another set.
     * @return bool {@see true} if the set has no elements in common with other; otherwise, {@see false}.
     */
    public function isDisjoint(SetAlgebra $other): bool;
}
