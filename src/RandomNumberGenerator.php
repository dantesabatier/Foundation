<?php

declare(strict_types=1);

namespace Sabatier\Foundation;

/**
 * A type that provides uniformly distributed random data.
 */
interface RandomNumberGenerator
{
    /**
     * Returns a value from a uniform, independent distribution of binary data.
     * @param int $upperBound The upper bound for the randomly generated value.
     * @return int A random value in the range from zero through `$upperBound`, inclusive. Every value in the range is equally likely to be returned.
     */
    public function next(int $upperBound = 0): int;
}
