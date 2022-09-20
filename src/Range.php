<?php

namespace Sabatier\Foundation;

use Countable;
use Generator;
use IteratorAggregate;
use JetBrains\PhpStorm\Deprecated;
use JetBrains\PhpStorm\Pure;
use Traversable;

/**
 * Class Range
 * A half-open interval from a lower bound up to, but not including, an upper bound.
 * @package Sabatier\Foundation
 * @implements IteratorAggregate<int, int>
 */
class Range extends ObjectClass implements ExpressibleByArrayLiteral, IteratorAggregate, Countable
{
    /**
     * @param int $lowerBound The range's lower bound.
     * @param int $upperBound The range's upper bound.
     */
    public function __construct(public readonly int $lowerBound, public readonly int $upperBound)
    {
        assert($this->lowerBound <= $this->upperBound, 'lower bound cannot be grater that the upper bound');
    }

    public function count(): int
    {
        return $this->upperBound - $this->lowerBound;
    }

    /**
     * A Boolean value indicating whether the range contains no elements.
     * An empty Range instance has equal lower and upper bounds.
     * @return bool
     */
    public function isEmpty(): bool
    {
        return $this->lowerBound == $this->upperBound;
    }

    /**
     * Returns a Boolean value indicating whether the given element is contained within the range.
     * Because Range represents a half-open range, a Range instance does not contain its upper bound. element is contained in the range if it is greater than or equal to the lower bound and less than the upper bound.
     * @param int $element The element to check for containment.
     * @return bool true if element is contained in the range; otherwise, false.
     */
    public function contains(int $element): bool
    {
        return !$this->isEmpty() && (($element >= $this->lowerBound) && ($element < $this->upperBound));
    }

    #[Deprecated('since Foundation 0.1, use contains() instead', '%class%->contains(%parameter0%)')]
    public function containsElement(int $element): bool
    {
        trigger_error(sprintf("%s() is deprecated, use contains() instead", __METHOD__), E_USER_DEPRECATED);
        return $this->contains($element);
    }

    public function getIterator(): Traversable
    {
        return (function (): Generator {
            for ($bound = $this->lowerBound; $bound < $this->upperBound; $bound++) {
                yield $bound;
            }
        })();
    }

    #[Pure]
    public function description(): string
    {
        return sprintf("[%s...<%s]", $this->lowerBound, $this->upperBound);
    }

    /**
     * @return int[]
     */
    #[Pure]
    public function toArray(): array
    {
        return range($this->lowerBound, $this->upperBound - 1);
    }

    #[Pure]
    public function jsonSerialize(): array
    {
        return $this->toArray();
    }
}