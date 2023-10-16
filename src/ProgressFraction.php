<?php

namespace Sabatier\Foundation;

/** @internal */
class ProgressFraction
{
    public function __construct(public float $completed = 0.0, public float $total = 0.0, public readonly bool $overflowed = false)
    {
    }
}
