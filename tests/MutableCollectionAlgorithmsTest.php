<?php

declare(strict_types=1);

namespace Sabatier\Foundation\Tests;

use AssertionError;
use PHPUnit\Framework\TestCase;
use Sabatier\Foundation\ArrayClass;

final class MutableCollectionAlgorithmsTest extends TestCase
{
    public function testRemoveDeletesTheFirstEqualElement(): void
    {
        $array = new ArrayClass(["a", "b", "a"]);

        $array->remove("a");

        $this->assertSame(["b", "a"], $array->array);
    }

    public function testRemoveLeavesTheCollectionUnchangedWhenTheElementIsAbsent(): void
    {
        /** @var ArrayClass<string> $array */
        $array = new ArrayClass(["a", "b"]);

        $array->remove("c");

        $this->assertSame(["a", "b"], $array->array);
    }

    public function testRemoveFirstDeletesTheRequestedNumberOfElements(): void
    {
        $array = new ArrayClass(["a", "b", "c", "d"]);

        $array->removeFirst(2);

        $this->assertSame(["c", "d"], $array->array);
    }

    public function testRemoveFirstAcceptsZeroAndTheCollectionCount(): void
    {
        $array = new ArrayClass(["a", "b"]);

        $array->removeFirst(0);
        $this->assertSame(["a", "b"], $array->array);

        $array->removeFirst(2);
        $this->assertSame([], $array->array);
    }

    public function testRemoveFirstRejectsAnInvalidCount(): void
    {
        $array = new ArrayClass(["a", "b"]);

        $this->expectException(AssertionError::class);

        $array->removeFirst(3);
    }

    public function testRemoveFirstRejectsANegativeCount(): void
    {
        $array = new ArrayClass(["a", "b"]);

        $this->expectException(AssertionError::class);

        $array->removeFirst(-1);
    }

    public function testRemoveLastDeletesTheRequestedNumberOfElements(): void
    {
        $array = new ArrayClass(["a", "b", "c", "d"]);

        $array->removeLast(2);

        $this->assertSame(["a", "b"], $array->array);
    }

    public function testRemoveLastAcceptsZeroAndTheCollectionCount(): void
    {
        $array = new ArrayClass(["a", "b"]);

        $array->removeLast(0);
        $this->assertSame(["a", "b"], $array->array);

        $array->removeLast(2);
        $this->assertSame([], $array->array);
    }

    public function testRemoveAllDeletesElementsMatchingThePredicate(): void
    {
        $array = new ArrayClass(["a", "b", "c", "d"]);
        $visited = [];

        $array->removeAll(function (string $element, int $index) use (&$visited): bool {
            $visited[] = [$element, $index];
            return $index % 2 === 0;
        });

        $this->assertSame([["a", 0], ["b", 1], ["c", 2], ["d", 3]], $visited);
        $this->assertSame(["b", "d"], $array->array);
    }

    public function testRemoveAllWithoutAPredicateClearsTheCollection(): void
    {
        $array = new ArrayClass(["a", "b", "c"]);

        $array->removeAll();

        $this->assertSame([], $array->array);
    }
}
