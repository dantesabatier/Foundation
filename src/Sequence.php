<?php

/**
 * Created by PhpStorm.
 * User: dante
 * Date: 17/07/20
 * Time: 13:10
 */

namespace Sabatier\Foundation;

use Closure;
use Countable;
use Sabatier\Foundation\Predicates\Predicate;
use Traversable;

/**
 * A type that provides sequential, iterated access to its elements.
 * @template Index of array-key
 * @template Element
 * @template-extends Traversable<Index, Element>
 */
interface Sequence extends Traversable, Countable, Comparable, ExpressibleByArrayLiteral
{
    /** @var int The number of elements in the sequence. */
    public int $count {
        get;
    }
    /** @var bool A Boolean value indicating whether the sequence is empty. */
    public bool $isEmpty {
        get;
    }
    /** @var Element|null $first The first element of the sequence. */
    public mixed $first {
        get;
    }

    /**
     * Returns a Sequence containing, in order, the elements of the sequence that satisfy the given predicate.
     *
     * Complexity: O(n), where n is the length of the sequence.
     * @param Closure(Element, Index=, bool=): bool $isIncluded
     * @return Sequence<Index, Element>
     */
    public function filter(Closure $isIncluded): Sequence;

    /**
     * Evaluates a given predicate against each object in the receiving and returns a new sequence containing the objects for which the predicate returns true.
     *
     * Complexity: O(n), where n is the length of the sequence.
     * @param Predicate $predicate The predicate against which to evaluate the receiving sequence's elements.
     * @return Sequence<Index, Element> A new sequence containing the objects in the receiving array for which predicate returns true.
     * Objects in the resulting array appear in the same order as they do in the receiver.
     */
    public function filtered(Predicate $predicate): Sequence;

    /**
     * Returns a Boolean value indicating whether the sequence contains an element that satisfies the given predicate.
     *
     * Complexity: O(n), where n is the length of the sequence.
     * @param Closure(Element, Index=): bool $predicate A closure that takes an element of the sequence as its argument and returns a Boolean value that indicates whether the passed element represents a match.
     * @return bool true if the sequence contains an element that satisfies predicate; otherwise, false.
     */
    public function contains(Closure $predicate): bool;

    /**
     * Returns a Boolean value indicating whether the sequence contains the given element.
     *
     * Complexity: O(n), where n is the length of the sequence.
     * Available when Element conforms to {@see Equatable}.
     * @param Element $element The element to find in the sequence.
     * @return bool true if the element was found in the sequence; otherwise, false.
     */
    public function containsElement(mixed $element): bool;

    /**
     * Returns a Boolean value indicating whether every element of a sequence satisfies a given predicate.
     *
     * Complexity: O(n), where n is the length of the sequence.
     * @param Closure(Element, Index=): bool $predicate A closure that takes an element of the sequence as its argument and returns a Boolean value that indicates whether the passed element satisfies a condition.
     * @return bool true if the sequence contains only elements that satisfy predicate; otherwise, false.
     */
    public function allSatisfy(Closure $predicate): bool;

    /**
     * Returns a Boolean value indicating whether this sequence and another sequence contain equivalent elements in the same order, using the given predicate as the equivalence test.
     *
     * Complexity: O(m), where m is the lesser of the length of the sequence and the length of $sequence.
     * @param Sequence<Index, Element> $sequence A sequence to compare to this sequence.
     * @param Closure(Element, Element): bool|null $areEquivalent A predicate that returns true if its two arguments are equivalent; otherwise, false.
     * @return bool true if this sequence and other contain equivalent items, using areEquivalent as the equivalence test; otherwise, false.
     */
    public function elementsEqual(Sequence $sequence, ?Closure $areEquivalent = null): bool;

    /**
     * Returns the first element of the sequence that satisfies the given predicate.
     *
     * Complexity: O(n), where n is the length of the sequence.
     * @param Closure(Element, Index=): bool|null $where A closure that takes an element of the sequence as its argument and returns a Boolean value indicating whether the element is a match.
     * @return Element|null The first element of the sequence that satisfies predicate, or nil if there is no element that satisfies predicate.
     */
    public function first(?Closure $where = null);

    /**
     * Returns the minimum element in the sequence.
     *
     * Complexity: O(n), where n is the length of the sequence.
     * @return Element|null The sequence's minimum element. If the sequence has no elements, returns nil.
     */
    public function min();

    /**
     * Returns the maximum element in the sequence.
     *
     * Complexity: O(n), where n is the length of the sequence.
     * @return Element|null The sequence's maximum element. If the sequence has no elements, returns nil.
     */
    public function max();

    /**
     * Returns the result of combining the elements of the sequence using the given closure.
     *
     * Use the {@see Sequence::reduce()} method to produce a single value from the elements of an entire sequence.
     * For example, you can use this method on an array of integers to filter adjacent equal entries or count frequencies.
     *
     * Complexity: O(n), where n is the length of the sequence.
     * @template Result
     * @param Result $initialResult The value to use as the initial accumulating value.
     * @param Closure(Result, Element, Index=): Result $updateAccumulatingResult A closure that updates the accumulating value with an element of the sequence.
     * @return Result The final accumulated value. If the sequence has no elements, the result is initialResult.
     */
    public function reduce(mixed $initialResult, Closure $updateAccumulatingResult);

    /**
     * Calculate the sum of values in a sequence.
     *
     * Complexity: O(n), where n is the length of the sequence.
     * @return float|int Returns the sum of values as an integer or float; 0 if the sequence is empty.
     */
    public function sum(): float|int;

    /**
     * @template Result
     * Returns a sequence containing the results of mapping the given closure over the sequence's elements.
     *
     * Complexity: O(n), where n is the length of the sequence.
     * @param Closure(Element, Index=): Result $transform
     * @return Sequence<Index, Result>
     */
    public function map(Closure $transform): Sequence;

    /**
     * @template Result
     * Returns a sequence containing the non-nil results of calling the given transformation with each element of this sequence.
     *
     * Complexity: O(n), where n is the length of the sequence.
     * @param Closure(Element, Index=): Result $transform
     * @return Sequence<Index, Result>
     */
    public function compactMap(Closure $transform): Sequence;

    /**
     * @template Result
     * Returns a sequence containing the concatenated results of calling the given transformation with each element of this sequence.
     *
     * Complexity: O(m + n), where n is the length of this sequence and m is the length of the result.
     * @param Closure(Element, Index=): iterable<Result> $transform
     * @return Sequence<Index, Result>
     */
    public function flatMap(Closure $transform): Sequence;
}
