<?php

namespace Sabatier\Foundation;

/** @internal */
class ProgressFraction
{
    public float $completed = 0.0;
    public float $total = 0.0;
    public readonly bool $overflowed;

    public function __construct()
    {
        $this->overflowed = false;
    }
}
