<?php

namespace Sabatier\Foundation;

/**
 * A type that can be initialized using an array literal.
 * Interface ExpressibleByArrayLiteral
 * @package Sabatier\Foundation
 */
interface ExpressibleByArrayLiteral
{
    public function toArray(): array;
}
