<?php

namespace Sabatier\Foundation;

/**
 * The system's default source of random data.
 */
class SystemRandomNumberGenerator implements RandomNumberGenerator
{
    public function next(int $upperBound = NotFound): int
    {
        /** @noinspection PhpUnhandledExceptionInspection */
        return random_int(0, $upperBound !== NotFound ? $upperBound : PHP_INT_MAX);
    }
}
