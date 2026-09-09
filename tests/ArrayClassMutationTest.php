<?php

declare(strict_types=1);

namespace Sabatier\Foundation\Tests;

use Override;
use PHPUnit\Framework\TestCase;
use Sabatier\Foundation\ArrayClass;
use Sabatier\Foundation\RandomNumberGenerator;
use Sabatier\Foundation\Range;
use Sabatier\Foundation\Slice;

final class ArrayClassMutationTest extends TestCase
{
    public function testInsertContentsUsesIterationOrderInsteadOfSourceIndices(): void
    {
        $source = new Slice(new ArrayClass(["zero", "one", "two", "three"]), new Range(1, 3));
        /** @var ArrayClass<string> $array */
        $array = new ArrayClass(["a", "b", "c"]);

        $array->insertContentsOf($source, 1);

        $this->assertSame(["a", "one", "two", "b", "c"], $array->array);
    }

    public function testAppendingContentsReturnsAnIndependentArray(): void
    {
        /** @var ArrayClass<int> $array */
        $array = new ArrayClass([1, 2]);

        $appended = $array->appendingContentsOf([3, 4]);

        $this->assertSame([1, 2], $array->array);
        $this->assertSame([1, 2, 3, 4], $appended->array);
    }

    public function testInsertContentsCanUseTheReceiverAsItsSource(): void
    {
        $array = new ArrayClass([1, 2, 3]);

        $array->insertContentsOf($array, 1);

        $this->assertSame([1, 1, 2, 3, 2, 3], $array->array);
    }

    public function testShuffledUsesTheGeneratorBoundsWithoutMutatingTheReceiver(): void
    {
        $array = new ArrayClass([1, 2, 3, 4]);
        $generator = new UpperBoundRandomNumberGenerator();

        $shuffled = $array->shuffled($generator);

        $this->assertSame([1, 2, 3, 4], $array->array);
        $this->assertSame([4, 1, 2, 3], $shuffled->array);
        $this->assertSame([3, 2, 1, 0], $generator->upperBounds);
    }

    public function testPartitionPreservesTheOrderWithinBothPartitions(): void
    {
        $array = new ArrayClass([1, 2, 3, 4, 5]);

        $partitionIndex = $array->partition(fn(int $element): bool => $element % 2 === 0);

        $this->assertSame(3, $partitionIndex);
        $this->assertSame([1, 3, 5, 2, 4], $array->array);
    }

    public function testSwapAtExchangesDistinctIndicesAndAcceptsTheSameIndex(): void
    {
        $array = new ArrayClass(["a", "b", "c"]);

        $array->swapAt(0, 2);
        $array->swapAt(1, 1);

        $this->assertSame(["c", "b", "a"], $array->array);
    }
}

/** @internal */
final class UpperBoundRandomNumberGenerator implements RandomNumberGenerator
{
    /** @var list<int> */
    public array $upperBounds = [];

    #[Override]
    public function next(int $upperBound = 0): int
    {
        $this->upperBounds[] = $upperBound;
        return $upperBound;
    }
}
