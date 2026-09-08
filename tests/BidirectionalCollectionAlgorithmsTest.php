<?php

declare(strict_types=1);

namespace Sabatier\Foundation\Tests;

use Override;
use PHPUnit\Framework\TestCase;
use Sabatier\Foundation\ArrayClass;
use Sabatier\Foundation\RandomNumberGenerator;

final class BidirectionalCollectionAlgorithmsTest extends TestCase
{
    public function testLastReturnsTheFinalElementOrNullWhenEmpty(): void
    {
        $this->assertSame("c", new ArrayClass(["a", "b", "c"])->last());
        $this->assertNull(new ArrayClass()->last());
    }

    public function testLastSearchesBackwardAndPassesTheOriginalIndex(): void
    {
        $array = new ArrayClass(["a", "b", "c", "d"]);
        $visited = [];

        $last = $array->last(function (string $element, int $index) use (&$visited): bool {
            $visited[] = [$element, $index];
            return $element === "b";
        });

        $this->assertSame("b", $last);
        $this->assertSame([["d", 3], ["c", 2], ["b", 1]], $visited);
    }

    public function testLastAndLastIndexReturnNullWhenNothingMatches(): void
    {
        $array = new ArrayClass([1, 2, 3]);

        $this->assertNull($array->last(fn(int $element): bool => $element > 3));
        $this->assertNull($array->lastIndex(fn(int $element): bool => $element > 3));
    }

    public function testRandomElementUsesTheGeneratorResultAsAnIndex(): void
    {
        $generator = new FixedRandomNumberGenerator(2);
        $array = new ArrayClass(["a", "b", "c"]);

        $this->assertSame("c", $array->randomElement($generator));
        $this->assertSame(2, $generator->upperBound);
        $this->assertSame(1, $generator->calls);
    }

    public function testRandomElementDoesNotCallTheGeneratorForAnEmptyCollection(): void
    {
        $generator = new FixedRandomNumberGenerator(0);

        $this->assertNull(new ArrayClass()->randomElement($generator));
        $this->assertSame(0, $generator->calls);
    }

    public function testReverseMutatesAndReturnsTheReceiver(): void
    {
        $array = new ArrayClass([1, 2, 3]);

        $result = $array->reverse();

        $this->assertSame($array, $result);
        $this->assertSame([3, 2, 1], $array->array);
    }

    public function testReversedReturnsAnIndependentReversedCollection(): void
    {
        $array = new ArrayClass([1, 2, 3]);

        $reversed = $array->reversed();

        $this->assertNotSame($array, $reversed);
        $this->assertSame([3, 2, 1], $reversed->array);
        $this->assertSame([1, 2, 3], $array->array);
    }
}

/** @internal */
final class FixedRandomNumberGenerator implements RandomNumberGenerator
{
    public int $calls = 0;
    public ?int $upperBound = null;

    public function __construct(private readonly int $result)
    {
    }

    #[Override]
    public function next(int $upperBound = 0): int
    {
        $this->calls += 1;
        $this->upperBound = $upperBound;
        return $this->result;
    }
}
