<?php

declare(strict_types=1);

namespace Sabatier\Foundation\Tests;

use PHPUnit\Framework\TestCase;
use Sabatier\Foundation\SystemRandomNumberGenerator;

final class SystemRandomNumberGeneratorTest extends TestCase
{
    public function testZeroUpperBoundAlwaysReturnsZero(): void
    {
        $generator = new SystemRandomNumberGenerator();

        $this->assertSame(0, $generator->next(0));
    }

    public function testValuesStayWithinTheInclusiveUpperBound(): void
    {
        $generator = new SystemRandomNumberGenerator();

        for ($iteration = 0; $iteration < 64; $iteration++) {
            $value = $generator->next(8);

            $this->assertGreaterThanOrEqual(0, $value);
            $this->assertLessThanOrEqual(8, $value);
        }
    }

    public function testDefaultUpperBoundProducesANonNegativeInteger(): void
    {
        $generator = new SystemRandomNumberGenerator();

        $this->assertGreaterThanOrEqual(0, $generator->next());
    }
}
