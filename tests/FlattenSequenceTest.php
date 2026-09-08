<?php

declare(strict_types=1);

namespace Sabatier\Foundation\Tests;

use PHPUnit\Framework\Attributes\TestWith;
use PHPUnit\Framework\TestCase;
use Sabatier\Foundation\ArrayClass;
use Sabatier\Foundation\FlattenSequence;
use Sabatier\Foundation\Predicates\Predicate;
use Sabatier\Foundation\Range;
use Sabatier\Foundation\Slice;

final class FlattenSequenceTest extends TestCase
{
    public function testFlattenSequenceRecursivelyConcatenatesSegments(): void
    {
        $flattened = new FlattenSequence(new ArrayClass([
            [1, [2, 3]],
            new ArrayClass([4, 5]),
        ]));

        $this->assertSame([1, 2, 3, 4, 5], $flattened->array);
        $this->assertSame([1, 2, 3, 4, 5], iterator_to_array($flattened, false));
        $this->assertSame(5, $flattened->count);
        $this->assertCount(5, $flattened);
        $this->assertFalse($flattened->isEmpty);
        $this->assertSame(1, $flattened->first);
        $this->assertSame([1, 2, 3, 4, 5], $flattened->jsonSerialize());
    }

    public function testEmptySegmentsProduceAnEmptySequence(): void
    {
        $flattened = new FlattenSequence(new ArrayClass([[], new ArrayClass()]));

        $this->assertSame([], $flattened->array);
        $this->assertSame(0, $flattened->count);
        $this->assertTrue($flattened->isEmpty);
        $this->assertNull($flattened->first);
    }

    #[TestWith([3])]
    public function testFlattenSequenceReflectsChangesToItsSegments(int $appended): void
    {
        $segment = $this->integerArray(1, 2);
        /** @var ArrayClass<mixed> $segments */
        $segments = new ArrayClass([$segment]);
        $flattened = new FlattenSequence($segments);

        $this->assertSame([1, 2], $flattened->array);
        $segment->append($appended);
        $this->assertSame([1, 2, $appended], iterator_to_array($flattened, false));
    }

    public function testAlgorithmsAcceptAFlattenedSlice(): void
    {
        $segments = new ArrayClass([["outside"], [1, 2], [3], ["outside"]]);
        $flattened = new FlattenSequence(new Slice($segments, new Range(1, 3)));

        $this->assertSame([10, 20, 30], $flattened->map(fn(int $value): int => $value * 10)->array);
        $this->assertSame([1, 3], $flattened->compactMap(fn(int $value): ?int => $value % 2 === 0 ? null : $value)->array);
        $this->assertSame([1, -1, 2, -2, 3, -3], $flattened->flatMap(fn(int $value): array => [$value, -$value])->array);
        $this->assertSame([2, 3], $flattened->filter(fn(int $value): bool => $value > 1)->array);
        $this->assertSame([2, 3], $flattened->filtered(Predicate::block(fn(int $value): bool => $value > 1))->array);
    }

    public function testSerializationPreservesTheBaseSequence(): void
    {
        $segments = new ArrayClass([["outside"], [1, 2], [3], ["outside"]]);
        $flattened = new FlattenSequence(new Slice($segments, new Range(1, 3)));
        $restored = unserialize(serialize($flattened));

        $this->assertInstanceOf(FlattenSequence::class, $restored);
        $this->assertSame([1, 2, 3], $restored->array);
    }

    /** @return ArrayClass<int> */
    private function integerArray(int ...$elements): ArrayClass
    {
        return new ArrayClass($elements);
    }
}
