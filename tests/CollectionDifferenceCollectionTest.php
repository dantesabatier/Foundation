<?php

declare(strict_types=1);

namespace Sabatier\Foundation\Tests;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Sabatier\Foundation\CollectionDifference;
use Sabatier\Foundation\CollectionDifferenceChange;
use Sabatier\Foundation\CollectionDifferenceChangeType;
use Sabatier\Foundation\FlattenSequence;
use Sabatier\Foundation\SortDescriptor;
use Sabatier\Foundation\UndefinedKeyException;

/**
 * Tests the collection surface a CollectionDifference inherits from
 * MutableCollectionAlgorithms — traversal, mutation and ArrayAccess — which
 * CollectionDifferenceTest does not exercise: that suite covers the diff semantics
 * (edit scripts, move inference, inverse) and leaves the container itself untested.
 *
 * Regression guards:
 *  - removeAll() with a predicate keeps the survivors. It used to raise "Cannot access
 *    protected property ArrayClass::$reserved": the algorithm read the filtered
 *    collection's own storage, and CollectionDifference::filter() answers with an
 *    ArrayClass, so the protected access crossed a class boundary. Reading through the
 *    public array fixes it for any collection whose filter() changes type;
 *  - reduce() accumulates through the by-reference parameter and ignores the closure's
 *    return value, which is what sum() relies on;
 *  - the mutators operate on the change list itself: append, insertAt, removeAt,
 *    remove, removeFirst/Last, popFirst/Last and the ArrayAccess writes;
 *  - reverse() mutates in place while reversed() leaves the receiver untouched;
 *  - valueForKey() is object KVC over the declared properties, not element access.
 */
final class CollectionDifferenceCollectionTest extends TestCase
{
    private function insertion(string $element, int $offset): CollectionDifferenceChange
    {
        return new CollectionDifferenceChange(CollectionDifferenceChangeType::insert, $element, $offset);
    }

    /** Three insertions at offsets 0, 1 and 2. */
    private function difference(): CollectionDifference
    {
        return new CollectionDifference([$this->insertion("a", 0), $this->insertion("b", 1), $this->insertion("c", 2)]);
    }

    /** A removal at offset 0 followed by insertions at 1 and 2, so the change types differ. */
    private function mixedDifference(): CollectionDifference
    {
        return new CollectionDifference([
            new CollectionDifferenceChange(CollectionDifferenceChangeType::remove, "a", 0),
            $this->insertion("b", 1),
            $this->insertion("c", 2),
        ]);
    }

    /**
     * @param iterable<CollectionDifferenceChange> $changes
     * @return list<int>
     */
    private function offsets(iterable $changes): array
    {
        $offsets = [];
        foreach ($changes as $change) {
            $offsets[] = $change->offset;
        }
        return $offsets;
    }

    public function testTheDifferenceReportsItsBoundsAsACollection(): void
    {
        $difference = $this->difference();

        $this->assertSame(3, $difference->count);
        $this->assertFalse($difference->isEmpty);
        $this->assertSame(0, $difference->startIndex);
        $this->assertSame(3, $difference->endIndex);
        $this->assertTrue(new CollectionDifference()->isEmpty);
    }

    public function testMembershipTestsRunOverTheChanges(): void
    {
        $difference = $this->difference();

        $this->assertTrue($difference->contains(fn(CollectionDifferenceChange $change): bool => $change->offset === 1));
        $this->assertFalse($difference->contains(fn(CollectionDifferenceChange $change): bool => $change->offset === 9));
        $this->assertTrue($difference->containsElement($difference[0]));
        $this->assertTrue($difference->allSatisfy(fn(CollectionDifferenceChange $change): bool => $change->type === CollectionDifferenceChangeType::insert));
        $this->assertFalse($difference->allSatisfy(fn(CollectionDifferenceChange $change): bool => $change->offset > 0));
    }

    public function testIndexLookupsFindTheFirstAndLastMatch(): void
    {
        $difference = $this->mixedDifference();

        $isInsertion = fn(CollectionDifferenceChange $change): bool => $change->type === CollectionDifferenceChangeType::insert;

        $this->assertSame(1, $difference->firstIndex($isInsertion));
        $this->assertSame(2, $difference->lastIndex($isInsertion));
        $this->assertSame(1, $difference->indexOf($difference[1]));
        $this->assertNull($difference->firstIndex(fn(CollectionDifferenceChange $change): bool => $change->offset === 9));
    }

    public function testFirstAndLastAnswerTheEndsOfTheChangeList(): void
    {
        $difference = $this->difference();

        $this->assertSame(0, $difference->first->offset);
        $this->assertSame(2, $difference->last->offset);
        $this->assertSame(1, $difference->first(fn(CollectionDifferenceChange $change): bool => $change->offset > 0)->offset);
        $this->assertNull($difference->first(fn(CollectionDifferenceChange $change): bool => $change->offset > 9));
    }

    public function testReduceAccumulatesThroughItsByReferenceParameter(): void
    {
        $difference = $this->difference();

        // reduce() follows Swift's reduce(into:): it hands the accumulator in by reference and answers with it, discarding whatever the closure returns. That is the contract sum() is built on.
        $total = $difference->reduce(0, fn(int &$accumulated, CollectionDifferenceChange $change): int => $accumulated += $change->offset);

        $this->assertSame(3, $total);
        $this->assertSame(3, $difference->sum(), "sum() adds the offsets through the same mechanism");
    }

    public function testTransformationsProduceAnArrayClass(): void
    {
        $difference = $this->difference();

        $this->assertSame([0, 1, 2], $difference->map(fn(CollectionDifferenceChange $change): int => $change->offset)->array);
        $this->assertSame([1, 2], $difference->compactMap(fn(CollectionDifferenceChange $change): ?int => $change->offset > 0 ? $change->offset : null)->array);
        $this->assertSame([0, 1, 2], $difference->flatMap(fn(CollectionDifferenceChange $change): array => [$change->offset])->array);
        $this->assertInstanceOf(FlattenSequence::class, $difference->joined());
    }

    public function testFilteringNarrowsToTheMatchingChanges(): void
    {
        $difference = $this->mixedDifference();

        $insertions = $difference->filter(fn(CollectionDifferenceChange $change): bool => $change->type === CollectionDifferenceChangeType::insert);

        $this->assertSame([1, 2], $this->offsets($insertions));
        $this->assertSame(3, $difference->count, "filtering leaves the receiver alone");
    }

    public function testOrderingAnswersACopyInTheRequestedOrder(): void
    {
        $difference = $this->difference();

        $this->assertSame([2, 1, 0], $this->offsets($difference->sort(fn(CollectionDifferenceChange $lhs, CollectionDifferenceChange $rhs): int => $rhs->offset <=> $lhs->offset)));
        $this->assertSame([2, 1, 0], $this->offsets($difference->sorted([new SortDescriptor("offset", false)])));
        $this->assertSame([0, 1, 2], $this->offsets($difference), "ordering leaves the receiver alone");
    }

    public function testReversedCopiesWhileReverseMutatesInPlace(): void
    {
        $difference = $this->difference();

        $this->assertSame([2, 1, 0], $this->offsets($difference->reversed()));
        $this->assertSame([0, 1, 2], $this->offsets($difference), "reversed() leaves the receiver alone");

        $difference->reverse();

        $this->assertSame([2, 1, 0], $this->offsets($difference), "reverse() rewrites the receiver");
    }

    /** @return iterable<string, array{int, list<int>}> */
    public static function dropProvider(): iterable
    {
        yield "drop first" => [1, [1, 2]];
        yield "drop none" => [0, [0, 1, 2]];
    }

    /** @param list<int> $expected */
    #[DataProvider("dropProvider")]
    public function testDropFirstSkipsTheLeadingChanges(int $count, array $expected): void
    {
        $this->assertSame($expected, $this->offsets($this->difference()->dropFirst($count)));
    }

    public function testDropLastAndDropWhileTrimTheChangeList(): void
    {
        $difference = $this->difference();

        $this->assertSame([0, 1], $this->offsets($difference->dropLast(1)));
        $this->assertSame([1, 2], $this->offsets($difference->drop(fn(CollectionDifferenceChange $change): bool => $change->offset < 1)));
    }

    public function testAppendAndInsertAtGrowTheChangeList(): void
    {
        $difference = $this->difference();

        $difference->append($this->insertion("d", 3));

        $this->assertSame([0, 1, 2, 3], $this->offsets($difference));

        $difference->insertAt($this->insertion("z", 9), 1);

        $this->assertSame([0, 9, 1, 2, 3], $this->offsets($difference));
    }

    public function testRemoveAtReturnsTheChangeItTakesOut(): void
    {
        $difference = $this->difference();

        $removed = $difference->removeAt(1);

        $this->assertSame(1, $removed->offset);
        $this->assertSame([0, 2], $this->offsets($difference));
    }

    public function testRemoveTakesOutTheGivenChange(): void
    {
        $difference = $this->difference();

        $difference->remove($difference[0]);

        $this->assertSame([1, 2], $this->offsets($difference));
    }

    public function testRemoveAllWithAPredicateKeepsTheSurvivors(): void
    {
        $difference = $this->difference();

        // The regression: filter() answers with an ArrayClass here, so reading its
        // protected storage from MutableCollectionAlgorithms raised an Error.
        $difference->removeAll(fn(CollectionDifferenceChange $change): bool => $change->offset > 0);

        $this->assertSame([0], $this->offsets($difference));
    }

    public function testRemoveAllWithoutAPredicateEmptiesTheDifference(): void
    {
        $difference = $this->difference();

        $difference->removeAll();

        $this->assertTrue($difference->isEmpty);
        $this->assertSame(0, $difference->count);
    }

    public function testRemoveFirstAndRemoveLastTrimTheEnds(): void
    {
        $fromFront = $this->difference();
        $fromBack = $this->difference();

        $fromFront->removeFirst(1);
        $fromBack->removeLast(1);

        $this->assertSame([1, 2], $this->offsets($fromFront));
        $this->assertSame([0, 1], $this->offsets($fromBack));
    }

    public function testPopReturnsTheChangeItRemoves(): void
    {
        $fromFront = $this->difference();
        $fromBack = $this->difference();

        $this->assertSame(0, $fromFront->popFirst()->offset);
        $this->assertSame([1, 2], $this->offsets($fromFront));
        $this->assertSame(2, $fromBack->popLast()->offset);
        $this->assertSame([0, 1], $this->offsets($fromBack));
    }

    public function testPoppingAnEmptyDifferenceAnswersNull(): void
    {
        $this->assertNull(new CollectionDifference()->popFirst());
        $this->assertNull(new CollectionDifference()->popLast());
    }

    public function testArrayAccessReadsWritesAndTests(): void
    {
        $difference = $this->difference();

        $this->assertTrue(isset($difference[0]));
        $this->assertFalse(isset($difference[9]));
        $this->assertSame(1, $difference[1]->offset);

        $difference[1] = $this->insertion("z", 7);

        $this->assertSame([0, 7, 2], $this->offsets($difference));
    }

    public function testValueForKeyReadsTheDeclaredPropertiesRatherThanElements(): void
    {
        $difference = $this->difference();

        $this->assertSame(3, $difference->valueForKey("count"));
        $this->assertFalse($difference->valueForKey("isEmpty"));
        $this->assertSame(3, $difference->valueForKey("insertions")->count);
    }

    public function testValueForAnUndeclaredKeyFallsThrough(): void
    {
        $this->expectException(UndefinedKeyException::class);

        $this->difference()->valueForKey("offset");
    }

    public function testRandomElementComesFromTheChangeList(): void
    {
        $difference = $this->difference();

        $element = $difference->randomElement();

        $this->assertInstanceOf(CollectionDifferenceChange::class, $element);
        $this->assertTrue($difference->containsElement($element));
    }
}
