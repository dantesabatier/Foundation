<?php

declare(strict_types=1);

namespace Sabatier\Foundation\Tests;

use PHPUnit\Framework\TestCase;
use Sabatier\Foundation\InternalInconsistencyException;
use Sabatier\Foundation\Range;

final class RangeTest extends TestCase
{
    public function testHalfOpenRangeExposesItsBoundsAndElements(): void
    {
        $range = new Range(2, 5);

        $this->assertSame(2, $range->lowerBound);
        $this->assertSame(5, $range->upperBound);
        $this->assertSame(3, $range->count);
        $this->assertCount(3, $range);
        $this->assertFalse($range->isEmpty);
        $this->assertSame("[2...<5]", $range->description);
        $this->assertSame([2, 3, 4], $range->array);
        $this->assertSame([2, 3, 4], iterator_to_array($range, false));
        $this->assertSame([2, 3, 4], $range->jsonSerialize());

        $restored = unserialize(serialize($range));
        $this->assertInstanceOf(Range::class, $restored);
        $this->assertSame(2, $restored->lowerBound);
        $this->assertSame(5, $restored->upperBound);
        $this->assertSame([2, 3, 4], $restored->array);
    }

    public function testEmptyRangeContainsNoElements(): void
    {
        $range = new Range(3, 3);

        $this->assertSame(0, $range->count);
        $this->assertTrue($range->isEmpty);
        $this->assertSame([], $range->array);
        $this->assertSame([], iterator_to_array($range, false));
        $this->assertSame([], $range->jsonSerialize());
        $this->assertFalse($range->contains(3));
    }

    public function testContainsIncludesOnlyValuesBeforeUpperBound(): void
    {
        $range = new Range(-2, 2);

        $this->assertFalse($range->contains(-3));
        $this->assertTrue($range->contains(-2));
        $this->assertTrue($range->contains(1));
        $this->assertFalse($range->contains(2));
    }

    public function testDescendingBoundsThrow(): void
    {
        $this->expectException(InternalInconsistencyException::class);

        new Range(2, 1);
    }
}
