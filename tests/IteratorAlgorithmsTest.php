<?php

declare(strict_types=1);

namespace Sabatier\Foundation\Tests;

use PHPUnit\Framework\TestCase;
use Sabatier\Foundation\ArrayClass;
use Sabatier\Foundation\IndexPath;
use Sabatier\Foundation\Set;

final class IteratorAlgorithmsTest extends TestCase
{
    public function testIteratorStartsAtTheFirstElementAndAdvances(): void
    {
        $array = new ArrayClass(["a", "b"]);

        $this->assertTrue($array->valid());
        $this->assertSame(0, $array->key());
        $this->assertSame("a", $array->current());

        $array->next();

        $this->assertTrue($array->valid());
        $this->assertSame(1, $array->key());
        $this->assertSame("b", $array->current());
    }

    public function testIteratorBecomesInvalidAfterTheLastElement(): void
    {
        $array = new ArrayClass(["a"]);

        $array->next();

        $this->assertFalse($array->valid());
        $this->assertSame(1, $array->key());
    }

    public function testRewindReturnsToTheFirstElement(): void
    {
        $array = new ArrayClass(["a", "b"]);
        $array->next();

        $array->rewind();

        $this->assertSame(0, $array->key());
        $this->assertSame("a", $array->current());
    }

    public function testForeachRewindsBeforeIterating(): void
    {
        $array = new ArrayClass(["a", "b", "c"]);
        $array->next();
        $array->next();

        $this->assertSame(["a", "b", "c"], iterator_to_array($array, false));
    }

    public function testCollectionsUsingTheTraitExposeTheSameCursorBehavior(): void
    {
        $collections = [
            new ArrayClass([1, 2]),
            new IndexPath([1, 2]),
            new Set([1, 2]),
        ];

        foreach ($collections as $collection) {
            $this->assertSame(1, $collection->current());
            $collection->next();
            $this->assertSame(1, $collection->key());
            $this->assertSame(2, $collection->current());
        }
    }
}
