<?php

namespace Sabatier\Foundation;

/**
 * A type that can be initialized using an array literal.
 */
interface ExpressibleByArrayLiteral
{
    public function toArray(): array;
}
