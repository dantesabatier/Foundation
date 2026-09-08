<?php

declare(strict_types=1);

namespace Sabatier\Foundation\Tests;

use PHPUnit\Framework\TestCase;
use Sabatier\Foundation\ArrayClass;
use Sabatier\Foundation\ObjectClass;
use Sabatier\Foundation\Range;
use Sabatier\Foundation\Slice;
use Sabatier\Foundation\SortDescriptor;

final class CollectionAlgorithmsTest extends TestCase
{
    public function testIndexNavigationAndDistanceUseTheCollectionsPositions(): void
    {
        $slice = new Slice(new ArrayClass(["a", "b", "c", "d"]), new Range(1, 3));
        $index = $slice->startIndex;

        $slice->formIndexAfter($index);

        $this->assertSame(2, $index);
        $this->assertSame(3, $slice->indexAfter($index));
        $this->assertSame(2, $slice->distance($slice->startIndex, $slice->endIndex));
        $this->assertSame(-2, $slice->distance($slice->endIndex, $slice->startIndex));
    }

    public function testFirstIndexStopsAtTheFirstMatch(): void
    {
        $array = new ArrayClass([1, 2, 3, 4]);
        $visited = [];

        $index = $array->firstIndex(function (int $element) use (&$visited): bool {
            $visited[] = $element;
            return $element >= 3;
        });

        $this->assertSame(2, $index);
        $this->assertSame([1, 2, 3], $visited);
    }

    public function testSortMutatesAndReturnsTheReceiver(): void
    {
        $array = new ArrayClass([3, 1, 2]);

        $result = $array->sort(fn(int $lhs, int $rhs): int => $rhs <=> $lhs);

        $this->assertSame($array, $result);
        $this->assertSame([3, 2, 1], $array->array);
    }

    public function testSortedUsesEachDescriptorWithoutMutatingTheReceiver(): void
    {
        $items = new ArrayClass([
            new CollectionItem("b", 1),
            new CollectionItem("a", 2),
            new CollectionItem("a", 1),
        ]);

        $sorted = $items->sorted([
            new SortDescriptor("group", true),
            new SortDescriptor("rank", false),
        ]);

        $this->assertSame(["a2", "a1", "b1"], $sorted->map(fn(CollectionItem $item): string => $item->group . $item->rank)->array);
        $this->assertSame(["b1", "a2", "a1"], $items->map(fn(CollectionItem $item): string => $item->group . $item->rank)->array);
    }

    public function testKeyValueCodingReadsAndWritesEveryElement(): void
    {
        $first = new CollectionItem("a", 1);
        $second = new CollectionItem("b", 2);
        $items = new ArrayClass([$first, $second]);

        $this->assertSame(["a", "b"], $items->valueForKey("group")->array);

        $items->setValueForKey("updated", "group");

        $this->assertSame("updated", $first->group);
        $this->assertSame("updated", $second->group);
    }
}

/** @internal */
final class CollectionItem extends ObjectClass
{
    public function __construct(public string $group, public int $rank)
    {
    }
}
