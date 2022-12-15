<?php

namespace Sabatier\Foundation;

use Random\Engine\Secure;
use Random\Randomizer;

/**
 * The system's default source of random data.
 */
class SystemRandomNumberGenerator implements RandomNumberGenerator
{
    public function next(int $upperBound = NotFound): int
    {
        $randomizer = new Randomizer(new Secure());
        return $randomizer->getInt(0, $upperBound !== NotFound ? $upperBound : PHP_INT_MAX);
    }
}
