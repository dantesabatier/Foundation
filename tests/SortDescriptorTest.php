<?php

declare(strict_types=1);

namespace Sabatier\Foundation\Tests;

use PHPUnit\Framework\TestCase;
use Sabatier\Foundation\ArrayClass;
use Sabatier\Foundation\ComparisonResult;
use Sabatier\Foundation\ObjectClass;
use Sabatier\Foundation\SortDescriptor;

/**
 * A minimal key-value-coding object: SortDescriptor rejects plain scalars and only
 * compares objects reachable through valueForKeyPath.
 */
final class SortableItem extends ObjectClass
{
    public function __construct(public int $rank)
    {
    }
}

/**
 * Tests for src/SortDescriptor.php.
 *
 * Regression guard:
 *  - the sort direction was inverted: compareObject multiplied the spaceship result by
 *    ComparisonResult::orderedAscending->value (-1) for an ascending descriptor, so an
 *    ascending descriptor produced descending order (and vice versa). PHP's <=> already
 *    matches the ascending ComparisonResult convention (-1 when the left operand is
 *    smaller == orderedAscending), so an ascending descriptor must return it unchanged.
 *    Since every consumer runs comparisons through compareObject/sorted (Core Data
 *    fetches among them), this shifted every ordered result the wrong way.
 */
final class SortDescriptorTest extends TestCase
{
    public function testAscendingComparesInAscendingOrder(): void
    {
        $descriptor = new SortDescriptor("rank", true);

        $this->assertSame(ComparisonResult::orderedAscending, $descriptor->compareObject(new SortableItem(1), new SortableItem(5)), "1 < 5 is orderedAscending");
        $this->assertSame(ComparisonResult::orderedDescending, $descriptor->compareObject(new SortableItem(5), new SortableItem(1)), "5 > 1 is orderedDescending");
        $this->assertSame(ComparisonResult::orderedSame, $descriptor->compareObject(new SortableItem(3), new SortableItem(3)), "equal values are orderedSame");
    }

    public function testDescendingReversesTheComparison(): void
    {
        $descriptor = new SortDescriptor("rank", false);

        $this->assertSame(ComparisonResult::orderedDescending, $descriptor->compareObject(new SortableItem(1), new SortableItem(5)), "descending flips 1 < 5 to orderedDescending");
        $this->assertSame(ComparisonResult::orderedAscending, $descriptor->compareObject(new SortableItem(5), new SortableItem(1)), "descending flips 5 > 1 to orderedAscending");
        $this->assertSame(ComparisonResult::orderedSame, $descriptor->compareObject(new SortableItem(3), new SortableItem(3)), "equal values stay orderedSame regardless of direction");
    }

    public function testSortedAppliesAnAscendingDescriptor(): void
    {
        $items = new ArrayClass([new SortableItem(5), new SortableItem(3), new SortableItem(1), new SortableItem(4), new SortableItem(2)]);

        $ranks = array_map(static fn(SortableItem $item): int => $item->rank, iterator_to_array($items->sorted(new ArrayClass([new SortDescriptor("rank", true)]))));
        $this->assertSame([1, 2, 3, 4, 5], $ranks, "an ascending descriptor sorts low to high");
    }

    public function testSortedAppliesADescendingDescriptor(): void
    {
        $items = new ArrayClass([new SortableItem(5), new SortableItem(3), new SortableItem(1), new SortableItem(4), new SortableItem(2)]);

        $ranks = array_map(static fn(SortableItem $item): int => $item->rank, iterator_to_array($items->sorted(new ArrayClass([new SortDescriptor("rank", false)]))));
        $this->assertSame([5, 4, 3, 2, 1], $ranks, "a descending descriptor sorts high to low");
    }

    public function testReversedSortDescriptorFlipsTheDirection(): void
    {
        $ascending = new SortDescriptor("rank", true);
        $reversed = $ascending->reversedSortDescriptor;

        $this->assertFalse($reversed->ascending, "reversing an ascending descriptor yields a descending one");
        $this->assertSame(ComparisonResult::orderedDescending, $reversed->compareObject(new SortableItem(1), new SortableItem(5)), "the reversed descriptor orders 1 after 5");
    }
}
