<?php

declare(strict_types=1);

namespace Sabatier\Foundation\Tests;

use PHPUnit\Framework\TestCase;
use Sabatier\Foundation\ArrayClass;
use Sabatier\Foundation\Range;
use Sabatier\Foundation\Slice;

final class RangeReplaceableCollectionAlgorithmsTest extends TestCase
{
    public function testRepeatingCreatesTheRequestedNumberOfElements(): void
    {
        $this->assertSame([], ArrayClass::repeating("x", 0)->array);
        $this->assertSame(["x", "x", "x"], ArrayClass::repeating("x", 3)->array);
    }

    public function testReplaceSubrangeWithTheSameNumberOfElements(): void
    {
        $array = $this->stringArray("a", "b", "c", "d");

        $array->replaceSubrange(new Range(1, 3), $this->stringArray("x", "y"));

        $this->assertSame(["a", "x", "y", "d"], $array->array);
    }

    public function testReplaceSubrangeCanExpandTheCollection(): void
    {
        $array = $this->stringArray("a", "b", "c");

        $array->replaceSubrange(new Range(1, 2), $this->stringArray("x", "y", "z"));

        $this->assertSame(["a", "x", "y", "z", "c"], $array->array);
    }

    public function testReplaceSubrangeCanContractTheCollection(): void
    {
        $array = $this->stringArray("a", "b", "c", "d");

        $array->replaceSubrange(new Range(1, 3), $this->stringArray("x"));

        $this->assertSame(["a", "x", "d"], $array->array);
    }

    public function testReplaceEmptySubrangeInsertsElements(): void
    {
        $array = $this->stringArray("a", "c");

        $array->replaceSubrange(new Range(1, 1), $this->stringArray("b"));
        $array->replaceSubrange(new Range(3, 3), $this->stringArray("d"));

        $this->assertSame(["a", "b", "c", "d"], $array->array);
    }

    public function testReplacementUsesTheElementsOrderInsteadOfTheirIndices(): void
    {
        $base = $this->stringArray("outside", "x", "y");
        $replacement = new Slice($base, new Range(1, 3));
        $array = $this->stringArray("a", "b", "c");

        $array->replaceSubrange(new Range(1, 2), $replacement);

        $this->assertSame(["a", "x", "y", "c"], $array->array);
    }

    public function testReplacementCanReadFromASliceOfTheSameCollection(): void
    {
        $array = $this->stringArray("a", "b", "c", "d");
        $replacement = new Slice($array, new Range(2, 4));

        $array->replaceSubrange(new Range(0, 2), $replacement);

        $this->assertSame(["c", "d", "c", "d"], $array->array);
    }

    public function testRemoveSubrangeRemovesEveryElementInsideItsBounds(): void
    {
        $array = $this->stringArray("a", "b", "c", "d");

        $array->removeSubrange(new Range(1, 3));

        $this->assertSame(["a", "d"], $array->array);
    }

    /** @return ArrayClass<string> */
    private function stringArray(string ...$elements): ArrayClass
    {
        return new ArrayClass($elements);
    }
}
