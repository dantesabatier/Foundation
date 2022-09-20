<?php

namespace Sabatier\Foundation;

/**
 * A type that provides uniformly distributed random data.
 */
interface RandomNumberGenerator
{
    /**
     * Returns a value from a uniform, independent distribution of binary data.
     * @param int $upperBound The upper bound for the randomly generated value.
     * @return int A random value of T in the range 0..<upperBound. Every value in the range 0..<upperBound is equally likely to be returned.
     */
    public function next(int $upperBound = 0): int;
}
