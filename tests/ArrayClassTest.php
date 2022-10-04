<?php

namespace Sabatier\Foundation\Test;

use PHPUnit\Framework\TestCase;
use Sabatier\Foundation\ArrayClass;
use Sabatier\Foundation\KeyValueCoding;
use Sabatier\Foundation\Number;
use Sabatier\Foundation\ObjectClass;
use Sabatier\Foundation\Predicate;
use Sabatier\Foundation\Set;

final class ArrayClassTest extends TestCase
{
    public ArrayClass $array;

    protected function setUp(): void
    {
        parent::setUp();
        $this->array = (new ArrayClass([1, 2, 3]))->map(fn(int|float $e): KeyValueCoding => new class (new Number($e)) extends ObjectClass {
            public function __construct(public readonly Number $amount)
            {
            }
        });
    }

    public function testCanProcessAverageKeyValueOperator(): void
    {
        $value = $this->array->valueForKeyPath("amount.@avg.value");
        self::assertInstanceOf(
            Number::class,
            $value
        );
        self::assertEquals(
            2,
            $value->intValue
        );
    }

    public function testCanProcessMaximumKeyValueOperator(): void
    {
        $value = $this->array->valueForKeyPath("amount.@max.value");
        self::assertInstanceOf(
            Number::class,
            $value
        );
        self::assertEquals(
            3,
            $value->intValue
        );
    }

    public function testCanProcessMinimumKeyValueOperator(): void
    {
        $value = $this->array->valueForKeyPath("amount.@min.value");
        self::assertInstanceOf(
            Number::class,
            $value
        );
        self::assertEquals(
            1,
            $value->intValue
        );
    }

    public function testCanProcessSumKeyValueOperator(): void
    {
        $value = $this->array->valueForKeyPath("amount.@sum.value");
        self::assertInstanceOf(
            Number::class,
            $value
        );
        self::assertEquals(
            6,
            $value->intValue
        );
    }

    public function testCanProcessUnionOfArraysKeyValueOperator(): void
    {
        $array = new ArrayClass([new ArrayClass([new Number(1)]), new ArrayClass([new Number(1)])]);
        $value = $array->valueForKeyPath("@unionOfArrays.value");
        self::assertInstanceOf(
            ArrayClass::class,
            $value
        );
        self::assertEquals(
            [1, 1],
            $value->toArray()
        );
    }

    public function testCanProcessDistinctUnionOfArraysKeyValueOperator(): void
    {
        $array = new ArrayClass([new ArrayClass([new Number(1)]), new ArrayClass([new Number(1)])]);
        $value = $array->valueForKeyPath("@distinctUnionOfArrays.value");
        self::assertInstanceOf(
            ArrayClass::class,
            $value
        );
        self::assertEquals(
            [1],
            $value->toArray()
        );
    }

    public function testCanProcessUnionOfSetsKeyValueOperator(): void
    {
        $array = new ArrayClass([new Set([new Number(1)]), new Set([new Number(1)])]);
        $value = $array->valueForKeyPath("@unionOfSets.intValue");
        self::assertInstanceOf(
            Set::class,
            $value
        );
        self::assertEquals(
            [1],
            $value->toArray()
        );
    }

    public function testCanProcessDistinctUnionOfSetsKeyValueOperator(): void
    {
        $array = new ArrayClass([new Set([new Number(1)]), new Set([new Number(1)])]);
        $value = $array->valueForKeyPath("@distinctUnionOfSets.value");
        self::assertInstanceOf(
            Set::class,
            $value
        );
        self::assertEquals(
            [1],
            $value->toArray()
        );
    }

    public function testCanProcessUnionOfObjectsKeyValueOperator(): void
    {
        $array = new ArrayClass([new ArrayClass([new Number(1)]), new ArrayClass([new Number(1)])]);
        $value = $array->valueForKeyPath("@unionOfObjects.intValue");
        self::assertInstanceOf(
            ArrayClass::class,
            $value
        );
        self::assertEquals(
            [1, 1],
            $value->toArray()
        );
    }

    public function testCanProcessDistinctUnionOfObjectsKeyValueOperator(): void
    {
        $array = new ArrayClass([new ArrayClass([new Number(1)]), new ArrayClass([new Number(1)])]);
        $value = $array->valueForKeyPath("@distinctUnionOfObjects.intValue");
        self::assertInstanceOf(
            ArrayClass::class,
            $value
        );
        self::assertEquals(
            [1],
            $value->toArray()
        );
    }

    public function testCanDropFirstElements(): void
    {
        self::assertEquals(
            2,
            $this->array->dropFirst(1)->count()
        );
    }

    public function testCanDropLastElements(): void
    {
        self::assertEquals(
            1,
            $this->array->dropLast(2)->count()
        );
    }

    public function testCanGetRandomElement(): void
    {
        self::assertInstanceOf(
            KeyValueCoding::class,
            $this->array->randomElement()
        );
    }

    public function testCanFilterUsingClosure(): void
    {
        self::assertEquals(
            1,
            $this->array->filter(fn(mixed $e): bool => $e->amount->value == 1)->count()
        );
    }

    public function testCanFilterUsingPredicate(): void
    {
        $predicate = Predicate::format("amount.value == 1");
        self::assertNotNull($predicate);
        self::assertEquals(
            1,
            $this->array->filtered($predicate)->count()
        );
    }

    public function testCanSplit(): void
    {
        $array = $this->array->split(fn(mixed $e): bool => $e->amount->value === 2);
        self::assertEquals(
            2,
            $array->count()
        );
        foreach ($array as $slice) {
            foreach ($slice as $item) {
                self::assertTrue($item->amount->value !== 2);
            }
        }
    }

    public function testCanBeUsedAsString(): void
    {
        self::assertEquals(
            '[1]',
            new ArrayClass([1])
        );
    }
}
