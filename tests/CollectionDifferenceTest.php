<?php

declare(strict_types=1);

namespace Sabatier\Foundation\Tests;

use PHPUnit\Framework\TestCase;
use Sabatier\Foundation\ArrayClass;
use Sabatier\Foundation\CollectionDifference;
use Sabatier\Foundation\CollectionDifferenceChange;
use Sabatier\Foundation\CollectionDifferenceChangeType;

/**
 * Collection differences must preserve order and multiplicity: membership alone cannot describe a transition when elements move or occur more than once. The emitted changes must also be safe to apply in iteration order, insertion and removal views must sort their offsets, inverse differences must restore the base state, and move inference must leave repeated elements unassociated.
 */
final class CollectionDifferenceTest extends TestCase
{
    /**
     * @param list<mixed> $source
     * @return list<mixed>
     */
    private function applying(CollectionDifference $difference, array $source): array
    {
        $result = new ArrayClass($source);
        foreach ($difference as $change) {
            switch ($change->type) {
                case CollectionDifferenceChangeType::insert:
                    $result->insertAt($change->element, $change->offset);
                    break;
                case CollectionDifferenceChangeType::remove:
                    $this->assertSame($change->element, $result[$change->offset]);
                    $result->removeAt($change->offset);
                    break;
                case CollectionDifferenceChangeType::move:
                    $this->assertNotNull($change->targetOffset);
                    $this->assertSame($change->element, $result[$change->offset]);
                    $result->removeAt($change->offset);
                    $result->insertAt($change->element, $change->targetOffset);
                    break;
            }
        }
        return $result->array;
    }

    /**
     * @param list<mixed> $source
     * @return list<mixed>
     */
    private function applyingInferredMoves(CollectionDifference $difference, array $source): array
    {
        $result = new ArrayClass($source);
        $removals = $difference->filter(fn(CollectionDifferenceChange $change): bool => $change->type === CollectionDifferenceChangeType::remove || $change->type === CollectionDifferenceChangeType::move);
        $removals->sort(fn(CollectionDifferenceChange $lhs, CollectionDifferenceChange $rhs): int => $rhs->offset <=> $lhs->offset);
        foreach ($removals as $removal) {
            $this->assertSame($removal->element, $result[$removal->offset]);
            $result->removeAt($removal->offset);
        }
        $insertions = $difference->compactMap(fn(CollectionDifferenceChange $change): ?CollectionDifferenceChange => match ($change->type) {
            CollectionDifferenceChangeType::insert => $change,
            CollectionDifferenceChangeType::remove => null,
            CollectionDifferenceChangeType::move => new CollectionDifferenceChange(CollectionDifferenceChangeType::insert, $change->element, $change->targetOffset ?? self::fail("A move requires a target offset.")),
        });
        $insertions->sort(fn(CollectionDifferenceChange $lhs, CollectionDifferenceChange $rhs): int => $lhs->offset <=> $rhs->offset);
        foreach ($insertions as $insertion) {
            $result->insertAt($insertion->element, $insertion->offset);
        }
        return $result->array;
    }

    /** @return list<array{CollectionDifferenceChangeType, mixed, int, int|null}> */
    private function changes(CollectionDifference $difference): array
    {
        return $difference->map(fn(CollectionDifferenceChange $change): array => [$change->type, $change->element, $change->offset, $change->targetOffset])->array;
    }

    public function testDifferenceIsAnApplicableOrderedEditScript(): void
    {
        $source = ["a", "b", "c", "d"];
        $target = new ArrayClass(["a", "x", "d", "y"]);
        $difference = $target->difference(new ArrayClass($source));
        $this->assertSame([
            [CollectionDifferenceChangeType::remove, "c", 2, null],
            [CollectionDifferenceChangeType::remove, "b", 1, null],
            [CollectionDifferenceChangeType::insert, "x", 1, null],
            [CollectionDifferenceChangeType::insert, "y", 3, null],
        ], $this->changes($difference));
        $this->assertSame($target->array, $this->applying($difference, $source));
        $this->assertSame([1, 2], $difference->removals->map(fn(CollectionDifferenceChange $change): int => $change->offset)->array);
        $this->assertSame([1, 3], $difference->insertions->map(fn(CollectionDifferenceChange $change): int => $change->offset)->array);
    }

    public function testDifferencePreservesDuplicateCounts(): void
    {
        $source = ["a", "a", "b"];
        $target = new ArrayClass(["a", "b", "b"]);
        $difference = $target->difference(new ArrayClass($source));
        $this->assertSame(["a"], $difference->removals->map(fn(CollectionDifferenceChange $change): mixed => $change->element)->array);
        $this->assertSame(["b"], $difference->insertions->map(fn(CollectionDifferenceChange $change): mixed => $change->element)->array);
        $this->assertSame($target->array, $this->applying($difference, $source));
    }

    public function testInsertionAndRemovalViewsSortTheirOffsets(): void
    {
        $difference = new CollectionDifference([
            new CollectionDifferenceChange(CollectionDifferenceChangeType::insert, "d", 3),
            new CollectionDifferenceChange(CollectionDifferenceChangeType::remove, "b", 1),
            new CollectionDifferenceChange(CollectionDifferenceChangeType::insert, "b", 1),
            new CollectionDifferenceChange(CollectionDifferenceChangeType::remove, "c", 2),
        ]);
        $this->assertSame([1, 3], $difference->insertions->map(fn(CollectionDifferenceChange $change): int => $change->offset)->array);
        $this->assertSame([1, 2], $difference->removals->map(fn(CollectionDifferenceChange $change): int => $change->offset)->array);
    }

    public function testDifferenceUsesTheProvidedEquivalenceTest(): void
    {
        $source = new ArrayClass(["ALPHA", "beta"]);
        $target = new ArrayClass(["alpha", "gamma"]);
        $arguments = [];
        $difference = $target->difference($source, function (string $base, string $candidate) use (&$arguments): bool {
            $arguments[] = [$base, $candidate];
            return strcasecmp($base, $candidate) === 0;
        });
        $this->assertSame([
            [CollectionDifferenceChangeType::remove, "beta", 1, null],
            [CollectionDifferenceChangeType::insert, "gamma", 1, null],
        ], $this->changes($difference));
        $this->assertContains(["ALPHA", "alpha"], $arguments);
    }

    public function testReorderingProducesAUniqueMove(): void
    {
        $source = ["a", "b", "c"];
        $target = new ArrayClass(["b", "a", "c"]);
        $difference = $target->difference(new ArrayClass($source));
        $this->assertSame($target->array, $this->applying($difference, $source));
        $moveDifference = $difference->inferringMoves();
        $this->assertSame(1, $moveDifference->count);
        $move = $moveDifference->first;
        $this->assertInstanceOf(CollectionDifferenceChange::class, $move);
        $this->assertSame(CollectionDifferenceChangeType::move, $move->type);
        $this->assertSame($target->array, $this->applyingInferredMoves($moveDifference, $source));
    }

    public function testMoveInferenceLeavesRepeatedElementsUnassociated(): void
    {
        $difference = new CollectionDifference([
            new CollectionDifferenceChange(CollectionDifferenceChangeType::remove, "a", 1),
            new CollectionDifferenceChange(CollectionDifferenceChangeType::remove, "a", 0),
            new CollectionDifferenceChange(CollectionDifferenceChangeType::insert, "a", 2),
            new CollectionDifferenceChange(CollectionDifferenceChangeType::insert, "a", 3),
        ]);
        $inferred = $difference->inferringMoves();
        $this->assertSame(0, $inferred->filter(fn(CollectionDifferenceChange $change): bool => $change->type === CollectionDifferenceChangeType::move)->count);
        $this->assertSame(2, $inferred->removals->count);
        $this->assertSame(2, $inferred->insertions->count);
    }

    public function testInverseRestoresBothOrdinaryChangesAndMoves(): void
    {
        $source = ["a", "b", "c", "d"];
        $target = new ArrayClass(["b", "c", "a", "e"]);
        $difference = $target->difference(new ArrayClass($source));
        $this->assertSame($source, $this->applying($difference->inverse(), $target->array));
        $moves = new ArrayClass(["b", "a", "c"])->difference(new ArrayClass(["a", "b", "c"]))->inferringMoves();
        $this->assertSame(["a", "b", "c"], $this->applyingInferredMoves($moves->inverse(), ["b", "a", "c"]));
    }

    public function testEverySmallDifferenceReconstructsItsTarget(): void
    {
        $sequences = [[]];
        for ($length = 1; $length <= 3; $length++) {
            $permutations = 3 ** $length;
            for ($permutation = 0; $permutation < $permutations; $permutation++) {
                $sequence = [];
                $value = $permutation;
                for ($position = 0; $position < $length; $position++) {
                    $sequence[] = match ($value % 3) {
                        0 => "a",
                        1 => "b",
                        2 => "c",
                    };
                    $value = intdiv($value, 3);
                }
                $sequences[] = $sequence;
            }
        }
        foreach ($sequences as $source) {
            foreach ($sequences as $target) {
                $difference = new ArrayClass($target)->difference(new ArrayClass($source));
                $this->assertSame($target, $this->applying($difference, $source));
                $this->assertSame($source, $this->applying($difference->inverse(), $target));
                $moves = $difference->inferringMoves();
                $this->assertSame($target, $this->applyingInferredMoves($moves, $source));
                $this->assertSame($source, $this->applyingInferredMoves($moves->inverse(), $target));
            }
        }
    }
}
