<?php

declare(strict_types=1);

namespace Sabatier\Foundation\Tests;

use PHPUnit\Framework\TestCase;
use Sabatier\Foundation\IndexPath;
use Sabatier\Foundation\InternalInconsistencyException;
use Sabatier\Foundation\Range;
use const Sabatier\Foundation\NotFound;

final class IndexPathTest extends TestCase
{
    public function testConstructionNormalizesPositions(): void
    {
        $path = new IndexPath([4 => 3, 9 => 5]);

        $this->assertSame([3, 5], $path->array);
        $this->assertSame(2, $path->count);
        $this->assertFalse($path->isEmpty);
        $this->assertSame(3, $path->first);
        $this->assertSame(5, $path->last);
        $this->assertSame(0, $path->startIndex);
        $this->assertSame(2, $path->endIndex);
        $this->assertSame([0, 1], $path->indices->array);
        $this->assertSame("[3, 5]", $path->description);
        $this->assertSame(3, $path->section);
        $this->assertSame(5, $path->item);
        $this->assertSame(5, $path->row);
    }

    public function testEmptyPathUsesNotFoundForMissingPositions(): void
    {
        $path = new IndexPath();

        $this->assertTrue($path->isEmpty);
        $this->assertNull($path->first);
        $this->assertNull($path->last);
        $this->assertSame(NotFound, $path->section);
        $this->assertSame(NotFound, $path->item);
        $this->assertSame(NotFound, $path->index(0));
    }

    public function testAppendingDoesNotMutateReceiver(): void
    {
        $path = new IndexPath([2, 4]);
        $appended = $path->appending(6);

        $this->assertSame([2, 4], $path->array);
        $this->assertSame([2, 4, 6], $appended->array);
    }

    public function testGetIndexesCopiesValuesAtPositionsInRange(): void
    {
        $path = new IndexPath([10, 20, 30, 40]);
        $indexes = null;

        $path->getIndexes($indexes, new Range(1, 3));
        $this->assertSame([20, 30], $indexes);

        $path->getIndexes($indexes, new Range(2, 2));
        $this->assertSame([], $indexes);
    }

    public function testFilterPreservesMatchingIndexes(): void
    {
        $filtered = new IndexPath([1, 2, 3, 4])->filter(fn(int $index): bool => $index % 2 === 0);

        $this->assertSame([2, 4], $filtered->array);
    }

    public function testTransformationsProduceExpectedElements(): void
    {
        $path = new IndexPath([1, 2, 3]);
        $mapped = $path->map(fn(int $index): int => $index * 10);
        $compactMapped = $path->compactMap(fn(int $index): ?int => $index % 2 === 0 ? null : $index);
        $flatMapped = $path->flatMap(fn(int $index): array => [$index, -$index]);

        $this->assertSame([10, 20, 30], $mapped->array);
        $this->assertSame([1, 3], $compactMapped->array);
        $this->assertSame([1, -1, 2, -2, 3, -3], $flatMapped->array);
    }

    public function testMutableOperationsPreservePositionOrder(): void
    {
        $path = new IndexPath([2, 4]);
        $path->insertAt(3, 1);
        $path->append(5);

        $this->assertSame(2, $path->removeAt(0));
        $this->assertSame([3, 4, 5], $path->array);
        $this->assertSame(5, $path->popLast());
        $this->assertSame([4, 3], $path->reverse()->array);
        $this->assertSame([4, 3], iterator_to_array($path, false));
    }

    public function testSubscriptOutsidePathThrows(): void
    {
        $this->expectException(InternalInconsistencyException::class);

        new IndexPath([3])[1];
    }
}
