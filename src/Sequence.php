<?php

/**
 * Created by PhpStorm.
 * User: dante
 * Date: 17/07/20
 * Time: 13:10
 */

namespace Sabatier\Foundation;

use Closure;
use Traversable;

/**
 * Interface Sequence
 * A type that provides sequential, iterated access to its elements.
 * @package Sabatier\Foundation
 * @template Index of array-key
 * @template Element
 * @template-extends Traversable<Index, Element>
 */
interface Sequence extends Traversable, ExpressibleByArrayLiteral
{
    /**
     * Returns a Boolean value indicating whether the sequence contains an element that satisfies the given predicate.
     * @param Closure(Element, Index=): bool $predicate A closure that takes an element of the sequence as its argument and returns a Boolean value that indicates whether the passed element represents a match.
     * @return bool true if the sequence contains an element that satisfies predicate; otherwise, false.
     */
    public function contains(Closure $predicate): bool;

    /**
     * Returns a bool value indicating whether the sequence contains an element that satisfies the given predicate.
     * Available when Element conforms to {@see Equatable}.
     * @param Element $element The element to find in the sequence.
     * @return bool {@see true} if the element was found in the sequence; otherwise, {@see false}.
     */
    public function containsElement(mixed $element): bool;

    /**
     * Returns a Boolean value indicating whether this sequence and another sequence contain equivalent elements in the same order, using the given predicate as the equivalence test.
     * @param Sequence $sequence A sequence to compare to this sequence.
     * @param Closure(Element, Element): bool|null $areEquivalent A predicate that returns true if its two arguments are equivalent; otherwise, false.
     * @return bool true if this sequence and other contain equivalent items, using areEquivalent as the equivalence test; otherwise, false.
     */
    public function elementsEqual(Sequence $sequence, ?Closure $areEquivalent = null): bool;

    /**
     * Returns the first element of the sequence that satisfies the given predicate.
     * @param Closure(Element, Index=): bool|null $where A closure that takes an element of the sequence as its argument and returns a Boolean value indicating whether the element is a match.
     * @return Element|null The first element of the sequence that satisfies predicate, or nil if there is no element that satisfies predicate.
     */
    public function first(Closure $where = null);

    /**
     * Returns the minimum element in the sequence.
     * @return Element|null The sequence's minimum element. If the sequence has no elements, returns nil.
     */
    public function min();

    /**
     * Returns the maximum element in the sequence.
     * @return Element|null The sequence's maximum element. If the sequence has no elements, returns nil.
     */
    public function max();

    /**
     * Returns the result of combining the elements of the sequence using the given closure.
     * Use the {@see Sequence::reduce()} method to produce a single value from the elements of an entire sequence.
     * For example, you can use this method on an array of integers to filter adjacent equal entries or count frequencies.
     * @template Result
     * @param Result $initialResult The value to use as the initial accumulating value.
     * @param Closure(Result, Element, Index=): Result $updateAccumulatingResult A closure that updates the accumulating value with an element of the sequence.
     * @return Result The final accumulated value. If the sequence has no elements, the result is initialResult.
     */
    public function reduce(mixed $initialResult, Closure $updateAccumulatingResult);

    /**
     * Calculate the sum of values in a sequence.
     * @return float|int Returns the sum of values as an integer or float; 0 if the sequence is empty.
     */
    public function sum(): float|int;

    /**
     * @template Result
     * Returns a sequence containing the results of mapping the given closure over the sequence's elements.
     * @param Closure(Element, Index=): Result $transform
     * @return Sequence<Index, Result>
     */
    public function map(Closure $transform): Sequence;

    /**
     * @template Result
     * Returns a sequence containing the non-nil results of calling the given transformation with each element of this sequence.
     * @param Closure(Element, Index=): Result $transform
     * @return Sequence<Index, Result>
     */
    public function compactMap(Closure $transform): Sequence;

    /**
     * @template Result
     * Returns a sequence containing the concatenated results of calling the given transformation with each element of this sequence.
     * @param Closure(Element, Index=): iterable<Result> $transform
     * @return Sequence<Index, Result>
     */
    public function flatMap(Closure $transform): Sequence;
}
