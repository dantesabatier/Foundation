<?php

declare(strict_types=1);

namespace Sabatier\Foundation;

/**
 * A type that presents a mathematical set interface to a bit set.
 */
class OptionSet
{
    public function __construct(private(set) int $rawValue = 0)
    {
    }

    /**
     * Returns a Boolean value that indicates whether the given element exists in the set.
     *
     * @param int $element The element to find in the sequence.
     * @return bool true if the element was found in the sequence; otherwise, false.
     */
    public function contains(int $element): bool
    {
        return ($this->rawValue & $element) === $element;
    }

    /**
     * Inserts the given element in the set if it is not already present.
     *
     * @param int $newElement An element to insert into the set.
     */
    public function insert(int $newElement): void
    {
        $this->rawValue |= $newElement;
    }

    /**
     * Removes the given element and any elements subsumed by the given element.
     * @param int $element
     */
    public function remove(int $element): void
    {
        $this->rawValue &= ~$element;
    }
}
