<?php

declare(strict_types=1);

namespace Sabatier\Foundation;

use Override;
use Random\Engine\Secure;
use Random\Randomizer;

/**
 * The system's default source of random data.
 */
final readonly class SystemRandomNumberGenerator implements RandomNumberGenerator
{
    private Randomizer $randomizer;

    public function __construct()
    {
        $this->randomizer = new Randomizer(new Secure());
    }
    
    #[Override]
    public function next(int $upperBound = NotFound): int
    {
        return $this->randomizer->getInt(0, $upperBound !== NotFound ? $upperBound : PHP_INT_MAX);
    }
}
