<?php

namespace Sabatier\Foundation;

use Exception;

/**
 * The system's default source of random data.
 */
class SystemRandomNumberGenerator implements RandomNumberGenerator
{
    public function next(int $upperBound = NotFound): int
    {
        $max = $upperBound !== NotFound ? $upperBound : PHP_INT_MAX;
        try {
            return random_int(0, $max);
        } catch(Exception) {
            return rand(0, $max);
        }
    }
}
