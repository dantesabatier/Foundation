<?php

declare(strict_types=1);

namespace Sabatier\Foundation\Tests;

use PHPUnit\Framework\Attributes\TestWith;
use PHPUnit\Framework\TestCase;
use Sabatier\Foundation\ArrayClass;
use Sabatier\Foundation\IndexPath;
use Sabatier\Foundation\InternalInconsistencyException;
use Sabatier\Foundation\Range;
use Sabatier\Foundation\Slice;

final class SliceTest extends TestCase
{
    public function testSlicePreservesBasePositionsWithinBounds(): void
    {
        $slice = new Slice(new ArrayClass(["a", "b", "c", "d"]), new Range(1, 3));

        $this->assertSame(2, $slice->count);
        $this->assertFalse($slice->isEmpty);
        $this->assertSame(1, $slice->startIndex);
        $this->assertSame(3, $slice->endIndex);
        $this->assertSame([1, 2], $slice->indices->array);
        $this->assertSame("b", $slice->first);
        $this->assertSame(["b", "c"], $slice->array);
        $this->assertSame([1 => "b", 2 => "c"], iterator_to_array($slice));
        $this->assertSame(["b", "c"], $slice->jsonSerialize());
        $this->assertSame(2, $slice->firstIndex(fn(string $element): bool => $element === "c"));
    }

    #[TestWith(["B"])]
    public function testSliceReflectsChangesToItsBase(string $replacement): void
    {
        $base = $this->stringArray("a", "b", "c");
        $slice = new Slice($base, new Range(1, 3));

        $this->assertSame(["b", "c"], $slice->array);
        $base[1] = $replacement;
        $this->assertSame([$replacement, "c"], $slice->array);
    }

    public function testSliceCanUseAnotherSliceAsItsBase(): void
    {
        $base = new ArrayClass(["a", "b", "c", "d", "e"]);
        $outer = new Slice($base, new Range(1, 4));
        $inner = new Slice($outer, new Range(2, 4));

        $this->assertSame(["c", "d"], $inner->array);
        $this->assertSame([2 => "c", 3 => "d"], iterator_to_array($inner));
    }

    public function testJoinedUsesOnlySegmentsInsideTheSlice(): void
    {
        $segments = new ArrayClass([
            new ArrayClass(["outside-before"]),
            new ArrayClass(["inside-a"]),
            new ArrayClass(["inside-b"]),
            new ArrayClass(["outside-after"]),
        ]);
        $slice = new Slice($segments, new Range(1, 3));

        $this->assertSame(["inside-a", "inside-b"], $slice->joined()->array);
    }

    public function testTransformationsAcceptAnyCompatibleBaseCollection(): void
    {
        $slice = new Slice(new IndexPath([1, 2, 3]), new Range(1, 3));

        $this->assertSame([20, 30], $slice->map(fn(int $index): int => $index * 10)->array);
        $this->assertSame([3], $slice->compactMap(fn(int $index): ?int => $index % 2 === 0 ? null : $index)->array);
        $this->assertSame([2, -2, 3, -3], $slice->flatMap(fn(int $index): array => [$index, -$index])->array);
    }

    public function testSerializationPreservesBaseAndBounds(): void
    {
        $slice = new Slice(new ArrayClass(["a", "b", "c"]), new Range(1, 3));
        $restored = unserialize(serialize($slice));

        $this->assertInstanceOf(Slice::class, $restored);
        $this->assertSame(["b", "c"], $restored->array);
        $this->assertSame(1, $restored->startIndex);
        $this->assertSame(3, $restored->endIndex);
    }

    #[TestWith([-1, 2])]
    #[TestWith([1, 4])]
    public function testBoundsMustStayInsideBase(int $lowerBound, int $upperBound): void
    {
        $this->expectException(InternalInconsistencyException::class);

        new Slice(new ArrayClass(["a", "b", "c"]), new Range($lowerBound, $upperBound));
    }

    /** @return ArrayClass<string> */
    private function stringArray(string ...$elements): ArrayClass
    {
        return new ArrayClass($elements);
    }
}
