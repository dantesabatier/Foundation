<?php

declare(strict_types=1);

namespace Sabatier\Foundation\Tests;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Sabatier\Foundation\FlattenSequence;
use Sabatier\Foundation\IndexPath;
use Sabatier\Foundation\InternalInconsistencyException;
use Sabatier\Foundation\UndefinedKeyException;

/**
 * Tests the collection surface IndexPath inherits from MutableCollectionAlgorithms.
 * IndexPathTest covers what a path means — normalizing positions, appending without
 * mutating, reading a range of positions — and leaves the container itself untested.
 *
 * Regression guards:
 *  - sort() mutates the receiver and answers it, while sorted() and reversed() copy.
 *    The two halves of that pair are easy to conflate, and CollectionDifference makes
 *    the opposite choice for sort(), so the asymmetry is worth pinning;
 *  - the mutators operate on the positions themselves: remove, removeAll with a
 *    predicate, removeFirst/Last and popFirst/Last;
 *  - valueForKey() reads the declared properties rather than a position, so a key that
 *    is not a property falls through to undefined-key;
 *  - makeObjectsPerformSelector() is unsupported: the elements are integers, and there
 *    is nothing to send a selector to.
 */
final class IndexPathCollectionTest extends TestCase
{
    /** A path through positions 1, 5 and 9. */
    private function path(): IndexPath
    {
        return new IndexPath([1, 5, 9]);
    }

    public function testMembershipTestsRunOverThePositions(): void
    {
        $path = $this->path();

        $this->assertTrue($path->contains(fn(int $position): bool => $position > 4));
        $this->assertFalse($path->contains(fn(int $position): bool => $position > 99));
        $this->assertTrue($path->containsElement(5));
        $this->assertFalse($path->containsElement(4));
        $this->assertTrue($path->allSatisfy(fn(int $position): bool => $position > 0));
        $this->assertFalse($path->allSatisfy(fn(int $position): bool => $position > 4));
    }

    public function testTheExtremesAndTheSumAreReadOffThePositions(): void
    {
        $path = $this->path();

        $this->assertSame(1, $path->min());
        $this->assertSame(9, $path->max());
        $this->assertSame(15, $path->reduce(0, fn(int &$accumulated, int $position): int => $accumulated += $position));
    }

    public function testIndexLookupsFindTheFirstAndLastMatch(): void
    {
        $path = $this->path();

        $this->assertSame(1, $path->firstIndex(fn(int $position): bool => $position > 4));
        $this->assertSame(2, $path->lastIndex(fn(int $position): bool => $position > 4));
        $this->assertNull($path->firstIndex(fn(int $position): bool => $position > 99));
        $this->assertSame(1, $path->indexOf(5));
        $this->assertNull($path->indexOf(99));
    }

    public function testIteratingVisitsEveryPositionInOrder(): void
    {
        $visited = [];

        $this->path()->forEach(function (int $position) use (&$visited): void {
            $visited[] = $position;
        });

        $this->assertSame([1, 5, 9], $visited);
    }

    public function testJoiningProducesAFlattenedView(): void
    {
        $this->assertInstanceOf(FlattenSequence::class, $this->path()->joined());
    }

    public function testARandomElementComesFromThePath(): void
    {
        $path = $this->path();

        $this->assertTrue($path->containsElement($path->randomElement()));
    }

    public function testSortRewritesTheReceiverWhileSortedCopies(): void
    {
        $sortedInPlace = new IndexPath([3, 1, 2]);

        $answer = $sortedInPlace->sort(fn(int $lhs, int $rhs): int => $lhs <=> $rhs);

        $this->assertSame([1, 2, 3], $sortedInPlace->array, "sort() rewrites the receiver");
        $this->assertSame($sortedInPlace, $answer, "and answers it rather than a copy");

        $copied = new IndexPath([3, 1, 2]);

        $copied->sorted([]);

        $this->assertSame([3, 1, 2], $copied->array, "sorted() leaves the receiver alone");
    }

    public function testReversedCopiesRatherThanRewriting(): void
    {
        $path = new IndexPath([1, 2, 3]);

        $reversed = $path->reversed();

        $this->assertSame([3, 2, 1], $reversed->array);
        $this->assertSame([1, 2, 3], $path->array, "the receiver keeps its order");
        $this->assertNotSame($path, $reversed);
    }

    public function testDroppingTrimsTheEndsOfThePath(): void
    {
        $path = $this->path();

        $this->assertSame([5, 9], array_values(iterator_to_array($path->dropFirst(1))));
        $this->assertSame([1, 5], array_values(iterator_to_array($path->dropLast(1))));
        $this->assertSame([5, 9], array_values(iterator_to_array($path->drop(fn(int $position): bool => $position < 5))));
        $this->assertSame([1, 5, 9], $path->array, "dropping leaves the receiver alone");
    }

    public function testRemovingTakesOutTheMatchingPositions(): void
    {
        $byValue = $this->path();
        $byPredicate = $this->path();

        $byValue->remove(5);
        $byPredicate->removeAll(fn(int $position): bool => $position > 4);

        $this->assertSame([1, 9], $byValue->array);
        $this->assertSame([1], $byPredicate->array);
    }

    public function testRemovingEveryPositionEmptiesThePath(): void
    {
        $path = $this->path();

        $path->removeAll();

        $this->assertTrue($path->isEmpty);
        $this->assertSame(0, $path->count);
    }

    public function testRemoveFirstAndRemoveLastTrimTheEnds(): void
    {
        $fromFront = $this->path();
        $fromBack = $this->path();

        $fromFront->removeFirst(1);
        $fromBack->removeLast(1);

        $this->assertSame([5, 9], $fromFront->array);
        $this->assertSame([1, 5], $fromBack->array);
    }

    public function testPopReturnsThePositionItRemoves(): void
    {
        $fromFront = $this->path();
        $fromBack = $this->path();

        $this->assertSame(1, $fromFront->popFirst());
        $this->assertSame([5, 9], $fromFront->array);
        $this->assertSame(9, $fromBack->popLast());
        $this->assertSame([1, 5], $fromBack->array);
    }

    public function testPoppingAnEmptyPathAnswersNull(): void
    {
        $this->assertNull(new IndexPath()->popFirst());
        $this->assertNull(new IndexPath()->popLast());
    }

    /** @return iterable<string, array{string, mixed}> */
    public static function declaredKeyProvider(): iterable
    {
        yield "count" => ["count", 3];
        yield "isEmpty" => ["isEmpty", false];
        yield "first" => ["first", 1];
        yield "last" => ["last", 9];
    }

    #[DataProvider("declaredKeyProvider")]
    public function testValueForKeyReadsTheDeclaredProperties(string $key, mixed $expected): void
    {
        $this->assertSame($expected, $this->path()->valueForKey($key));
        $this->assertSame($expected, $this->path()->valueForKeyPath($key));
    }

    public function testValueForAnUndeclaredKeyFallsThrough(): void
    {
        $this->expectException(UndefinedKeyException::class);

        $this->path()->valueForKey("nope");
    }

    public function testSendingASelectorToThePositionsIsUnsupported(): void
    {
        $this->expectException(InternalInconsistencyException::class);

        $this->path()->makeObjectsPerformSelector("anySelector");
    }
}
