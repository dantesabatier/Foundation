<?php

namespace Sabatier\Foundation;

use Countable;
use Generator;
use IteratorAggregate;
use JetBrains\PhpStorm\Deprecated;
use Override;
use Traversable;

/**
 * A half-open interval from a lower bound up to, but not including, an upper bound.
 * @implements IteratorAggregate<int, int>
 */
final class Range extends ObjectClass implements ExpressibleByArrayLiteral, IteratorAggregate, Countable
{
    /** @var int The number of elements in the collection. */
    public int $count {
        get => $this->upperBound - $this->lowerBound;
    }
    /** @var bool A Boolean value indicating whether the range contains no elements. */
    public bool $isEmpty {
        get => $this->lowerBound === $this->upperBound;
    }
    public string $description {
        get => "[$this->lowerBound...<$this->upperBound]";
    }
    /** @var list<int> */
    private(set) array $array {
        get => $this->array ??= range($this->lowerBound, $this->upperBound - 1);
    }

    /**
     * @param int $lowerBound The range's lower bound.
     * @param int $upperBound The range's upper bound.
     */
    public function __construct(public readonly int $lowerBound, public readonly int $upperBound)
    {
        $upperBound >= $lowerBound ?: fatal_error("Range error: lower bound cannot be greater that the upper bound");
    }

    #[Override]
    public function count(): int
    {
        return $this->count;
    }

    /**
     * Returns a Boolean value indicating whether the given element is contained within the range.
     *
     * Because Range represents a half-open range, a Range instance does not contain its upper bound $element is contained in the range if it is greater than or equal to the lower bound and less than the upper bound.
     * @param int $element The element to check for containment.
     * @return bool true if $element is contained in the range; otherwise, false.
     */
    public function contains(int $element): bool
    {
        return !$this->isEmpty && (($element >= $this->lowerBound) && ($element < $this->upperBound));
    }

    #[Deprecated("since Foundation 0.1, use contains() instead", "%class%->contains(%parameter0%)")]
    public function containsElement(int $element): bool
    {
        trigger_error(sprintf("%s() is deprecated, use contains() instead", __METHOD__), E_USER_DEPRECATED);
        return $this->contains($element);
    }

    #[Override]
    public function getIterator(): Traversable
    {
        return (function (): Generator {
            for ($bound = $this->lowerBound; $bound < $this->upperBound; $bound++) {
                yield $bound;
            }
        })();
    }

    #[Override]
    public function jsonSerialize(): array
    {
        return $this->array;
    }
}
