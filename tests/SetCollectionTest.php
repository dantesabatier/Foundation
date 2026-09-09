<?php

declare(strict_types=1);

namespace Sabatier\Foundation\Tests;

use PHPUnit\Framework\TestCase;
use Sabatier\Foundation\ArrayClass;
use Sabatier\Foundation\Set;
use Sabatier\Foundation\SystemRandomNumberGenerator;

/**
 * Tests the ordered collection surface of Set — the half it inherits rather than the
 * set algebra SetAlgebraAlgorithmsTest already covers.
 *
 * Regression guards:
 *  - uniqueness survives the ordered operations: insertAt() drops an element the set
 *    already holds instead of storing it twice;
 *  - update() answers the element it replaced, or null when the element is new, which
 *    is what separates it from insert();
 *  - sort() is an in-place sort answering the receiver, while sorted(), reversed() and
 *    shuffled() copy — and shuffled() answers an ArrayClass, since a shuffled set would
 *    be a contradiction;
 *  - the mutators keep working through the uniqueness check: removeAll with a
 *    predicate, removeFirst/Last, popFirst/Last and setSet.
 */
final class SetCollectionTest extends TestCase
{
    /** A set holding 3, 1 and 2, in that insertion order. */
    private function set(): Set
    {
        return new Set([3, 1, 2]);
    }

    public function testInsertingAtAPositionRespectsUniqueness(): void
    {
        $withNewElement = $this->set();
        $withDuplicate = $this->set();

        $withNewElement->insertAt(9, 1);
        $withDuplicate->insertAt(3, 0);

        $this->assertSame([3, 9, 1, 2], $withNewElement->array, "a new element lands at the requested position");
        $this->assertSame([3, 1, 2], $withDuplicate->array, "an element already present is not stored twice");
    }

    public function testUpdateAnswersTheElementItReplaced(): void
    {
        $set = $this->set();

        $this->assertNull($set->update(7), "a new element replaces nothing");
        $this->assertSame([3, 1, 2, 7], $set->array);
        $this->assertSame(1, $set->update(1), "an existing element is answered as the one replaced");
        $this->assertSame([3, 1, 2, 7], $set->array, "and the membership is unchanged");
    }

    public function testSortIsInPlaceWhileSortedCopies(): void
    {
        $sortedInPlace = $this->set();

        $answer = $sortedInPlace->sort(fn(int $lhs, int $rhs): int => $lhs <=> $rhs);

        $this->assertSame([1, 2, 3], $sortedInPlace->array, "sort() reorders the receiver in place");
        $this->assertSame($sortedInPlace, $answer, "and answers it so the call can be chained");

        $copied = $this->set();

        $this->assertInstanceOf(Set::class, $copied->sorted([]));
        $this->assertSame([3, 1, 2], $copied->array, "sorted() leaves the receiver alone");
    }

    public function testReverseIsInPlaceWhileReversedCopies(): void
    {
        $reversedInPlace = $this->set();
        $copied = $this->set();

        $reversedInPlace->reverse();

        $this->assertSame([2, 1, 3], $reversedInPlace->array);
        $this->assertSame([2, 1, 3], $copied->reversed()->array);
        $this->assertSame([3, 1, 2], $copied->array, "reversed() leaves the receiver alone");
    }

    public function testShufflingAnswersAnOrderedCopy(): void
    {
        $set = $this->set();

        $shuffled = $set->shuffled(new SystemRandomNumberGenerator());

        $this->assertInstanceOf(ArrayClass::class, $shuffled, "a shuffled set would be a contradiction, so the copy is ordered");
        $this->assertSame(3, $shuffled->count);
        $this->assertSame([3, 1, 2], $set->array, "the receiver keeps its order");
    }

    public function testTransformationsProduceTheExpectedElements(): void
    {
        $set = $this->set();

        $this->assertSame([30, 20], $set->compactMap(fn(int $element): ?int => $element > 1 ? $element * 10 : null)->array);
        $this->assertSame([3, 1, 2], $set->flatMap(fn(int $element): array => [$element])->array);
        $this->assertInstanceOf(Set::class, $set->filter(fn(int $element): bool => $element > 1));
    }

    public function testTheEndOfTheSetIsReadable(): void
    {
        $set = $this->set();

        $this->assertSame(2, $set->last);
        $this->assertSame(2, $set->lastIndex(fn(int $element): bool => $element > 1));
        $this->assertTrue($set->containsElement($set->randomElement()));
    }

    public function testDroppingTrimsWithoutTouchingTheReceiver(): void
    {
        $set = $this->set();

        $this->assertSame([1, 2], array_values(iterator_to_array($set->dropFirst(1))));
        $this->assertSame([3, 1], array_values(iterator_to_array($set->dropLast(1))));
        $this->assertSame([3, 1, 2], $set->array);
    }

    public function testRemovingByPredicateKeepsTheSurvivors(): void
    {
        $set = $this->set();

        $set->removeAll(fn(int $element): bool => $element > 1);

        $this->assertSame([1], $set->array);
    }

    public function testRemovingEverythingEmptiesTheSet(): void
    {
        $set = $this->set();

        $set->removeAll();

        $this->assertTrue($set->isEmpty);
    }

    public function testRemoveFirstAndRemoveLastTrimTheEnds(): void
    {
        $fromFront = $this->set();
        $fromBack = $this->set();

        $fromFront->removeFirst(1);
        $fromBack->removeLast(1);

        $this->assertSame([1, 2], $fromFront->array);
        $this->assertSame([3, 1], $fromBack->array);
    }

    public function testPopReturnsTheElementItRemoves(): void
    {
        $fromFront = $this->set();
        $fromBack = $this->set();

        $this->assertSame(3, $fromFront->popFirst());
        $this->assertSame([1, 2], $fromFront->array);
        $this->assertSame(2, $fromBack->popLast());
        $this->assertSame([3, 1], $fromBack->array);
    }

    public function testSetSetReplacesTheWholeMembership(): void
    {
        $set = $this->set();

        $set->setSet(new Set([8, 9]));

        $this->assertSame([8, 9], $set->array);
    }
}
