<?php

declare(strict_types=1);

namespace Sabatier\Foundation\Tests;

use PHPUnit\Framework\TestCase;
use Sabatier\Foundation\ArrayClass;
use Sabatier\Foundation\CompareOptions;
use Sabatier\Foundation\Dictionary;
use Sabatier\Foundation\Range;
use Sabatier\Foundation\Sequence;
use Sabatier\Foundation\Set;
use Sabatier\Foundation\Slice;

use function Sabatier\Foundation\string_is_equal;

final class SequenceAlgorithmsTest extends TestCase
{
    public function testElementsEqualComparesValuesInSequenceOrder(): void
    {
        $slice = new Slice(new ArrayClass(["outside", "A", "B"]), new Range(1, 3));
        /** @var Sequence<array-key, mixed> $same */
        $same = new ArrayClass(["A", "B"]);
        /** @var Sequence<array-key, mixed> $caseInsensitive */
        $caseInsensitive = new ArrayClass(["a", "b"]);
        /** @var Sequence<array-key, mixed> $reordered */
        $reordered = new ArrayClass(["B", "A"]);
        /** @var Sequence<array-key, mixed> $shorter */
        $shorter = new ArrayClass(["A"]);

        $this->assertTrue($slice->elementsEqual($same));
        $this->assertTrue($slice->elementsEqual(
            $caseInsensitive,
            fn(mixed $left, mixed $right): bool => string_is_equal((string)$left, (string)$right, CompareOptions::caseInsensitive),
        ));
        $this->assertFalse($slice->elementsEqual($reordered));
        $this->assertFalse($slice->elementsEqual($shorter));
    }

    public function testArrayStartsWithEquivalentElementsInSequenceOrder(): void
    {
        $letters = new ArrayClass(["A", "B", "C"]);

        $this->assertTrue($letters->starts(new ArrayClass()));
        $this->assertTrue($letters->starts(new ArrayClass(["A", "B"])));
        $this->assertTrue($letters->starts(new ArrayClass(["A", "B", "C"])));
        $this->assertFalse($letters->starts(new ArrayClass(["A", "C"])));
        $this->assertFalse($letters->starts(new ArrayClass(["A", "B", "C", "D"])));
        $this->assertTrue($letters->starts(
            new ArrayClass(["a", "b"]),
            fn(string $left, string $right): bool => string_is_equal($left, $right, CompareOptions::caseInsensitive),
        ));
        $this->assertTrue(new ArrayClass()->starts(new ArrayClass()));
        $this->assertFalse(new ArrayClass()->starts(new ArrayClass(["A"])));
    }

    public function testArrayLexicographicallyPrecedesAnotherSequence(): void
    {
        $this->assertTrue(new ArrayClass([1, 2])->lexicographicallyPrecedes(new ArrayClass([1, 3])));
        $this->assertFalse(new ArrayClass([1, 3])->lexicographicallyPrecedes(new ArrayClass([1, 2])));
        $this->assertTrue(new ArrayClass([1, 2])->lexicographicallyPrecedes(new ArrayClass([1, 2, 0])));
        $this->assertFalse(new ArrayClass([1, 2, 0])->lexicographicallyPrecedes(new ArrayClass([1, 2])));
        $this->assertFalse(new ArrayClass([1, 2])->lexicographicallyPrecedes(new ArrayClass([1, 2])));
        $this->assertTrue(new ArrayClass()->lexicographicallyPrecedes(new ArrayClass([1])));
        $this->assertFalse(new ArrayClass()->lexicographicallyPrecedes(new ArrayClass()));
        $this->assertTrue(new ArrayClass([3, 2])->lexicographicallyPrecedes(
            new ArrayClass([3, 1]),
            fn(int $left, int $right): bool => $left > $right,
        ));
    }

    public function testDictionaryEqualityUsesKeysAndValuesWithoutDependingOnOrder(): void
    {
        $dictionary = new Dictionary(["a" => 1, "b" => 2]);

        $this->assertTrue($dictionary->isEqual(new Dictionary(["b" => 2, "a" => 1])));
        $this->assertFalse($dictionary->isEqual(new Dictionary(["x" => 1, "b" => 2])));
        $this->assertFalse($dictionary->isEqual(new ArrayClass([1, 2])));
    }

    public function testSetEqualityIgnoresOrderAndRequiresAnotherSet(): void
    {
        $set = new Set([1, 2, 3]);

        $this->assertTrue($set->isEqual(new Set([3, 1, 2])));
        $this->assertFalse($set->isEqual(new Set([1, 2, 4])));
        $this->assertFalse($set->isEqual(new ArrayClass([1, 2, 3])));
    }
}
