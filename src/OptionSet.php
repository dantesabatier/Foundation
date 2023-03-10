<?php

namespace Sabatier\Foundation;

/**
 * A type that presents a mathematical set interface to a bit set.
 */
class OptionSet
{
    public function __construct(public int $rawValue = 0)
    {
    }

    public function contains(int $v): bool
    {
        return ($this->rawValue & $v) === $v;
    }

    public function insert(int $v): void
    {
        $this->rawValue |= $v;
    }

    public function remove(int $v): void
    {
        $this->rawValue &= ~$v;
    }
}