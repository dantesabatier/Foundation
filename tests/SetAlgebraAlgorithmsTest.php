<?php

declare(strict_types=1);

namespace Sabatier\Foundation\Tests;

use PHPUnit\Framework\TestCase;
use Sabatier\Foundation\Set;

final class SetAlgebraAlgorithmsTest extends TestCase
{
    public function testUnionHasMutatingAndNonmutatingForms(): void
    {
        $set = $this->integerSet(1, 2);

        $this->assertSetEquals([1, 2, 3], $set->union([2, 3, 3]));
        $this->assertSetEquals([1, 2], $set);

        $set->formUnion([2, 3, 3]);

        $this->assertSetEquals([1, 2, 3], $set);
    }

    public function testIntersectionHasMutatingAndNonmutatingForms(): void
    {
        $set = $this->integerSet(1, 2, 3);
        $other = $this->integerSet(2, 3, 4);

        $this->assertSetEquals([2, 3], $set->intersection($other));
        $this->assertSetEquals([1, 2, 3], $set);

        $set->formIntersection($other);

        $this->assertSetEquals([2, 3], $set);
    }

    public function testSymmetricDifferenceHasMutatingAndNonmutatingForms(): void
    {
        $set = $this->integerSet(1, 2, 3);
        $other = $this->integerSet(2, 3, 4);

        $this->assertSetEquals([1, 4], $set->symmetricDifference($other));
        $this->assertSetEquals([1, 2, 3], $set);

        $set->formSymmetricDifference($other);

        $this->assertSetEquals([1, 4], $set);
    }

    public function testSubtractionHasMutatingAndNonmutatingForms(): void
    {
        $set = $this->integerSet(1, 2, 3);
        $other = $this->integerSet(2, 4);

        $this->assertSetEquals([1, 3], $set->subtracting($other));
        $this->assertSetEquals([1, 2, 3], $set);

        $set->subtract($other);

        $this->assertSetEquals([1, 3], $set);
    }

    public function testSubsetIncludesEqualAndProperSubsets(): void
    {
        $set = $this->integerSet(1, 2);

        $this->assertTrue($set->isSubset($this->integerSet(1, 2)));
        $this->assertTrue($set->isSubset($this->integerSet(1, 2, 3)));
        $this->assertFalse($set->isSubset($this->integerSet(1, 3)));
        $this->assertTrue($this->integerSet()->isSubset($set));
        $this->assertTrue($this->integerSet()->isSubset($this->integerSet()));
    }

    public function testSupersetIncludesEqualAndProperSupersets(): void
    {
        $set = $this->integerSet(1, 2, 3);

        $this->assertTrue($set->isSuperset($this->integerSet(1, 2, 3)));
        $this->assertTrue($set->isSuperset($this->integerSet(1, 2)));
        $this->assertFalse($set->isSuperset($this->integerSet(1, 4)));
        $this->assertTrue($set->isSuperset($this->integerSet()));
        $this->assertTrue($this->integerSet()->isSuperset($this->integerSet()));
    }

    public function testDisjointSetsHaveNoEquivalentMembers(): void
    {
        $set = $this->integerSet(1, 2);

        $this->assertTrue($set->isDisjoint($this->integerSet(3, 4)));
        $this->assertFalse($set->isDisjoint($this->integerSet(2, 3)));
        $this->assertTrue($set->isDisjoint($this->integerSet()));
        $this->assertTrue($this->integerSet()->isDisjoint($set));
    }

    /** @return Set<int> */
    private function integerSet(int ...$elements): Set
    {
        return new Set(array_values($elements));
    }

    /** @param list<int> $expected */
    private function assertSetEquals(array $expected, Set $actual): void
    {
        $this->assertTrue($actual->isEqual(new Set($expected)));
    }
}
