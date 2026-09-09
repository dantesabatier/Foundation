<?php

declare(strict_types=1);

namespace Sabatier\Foundation\Tests;

use PHPUnit\Framework\TestCase;
use Sabatier\Foundation\Dictionary;
use Sabatier\Foundation\FlattenSequence;

/**
 * Tests the sequence surface of Dictionary — the operations it inherits that run over
 * its values rather than its keys, which CollectionsTest does not reach.
 *
 * Regression guards:
 *  - sort() sorts by value but keeps every key attached to the value it belongs to: it
 *    is uasort() underneath, not usort(), so a dictionary never degrades into a list;
 *  - sort() is in-place and answers the receiver, while sorted() copies — the same pair
 *    the ordered collections have;
 *  - the value-oriented operations (containsElement, min, max, flatMap, join) read
 *    values and ignore keys;
 *  - uniquingKeys() takes a keyed sequence, not a sequence of pairs, and its closure is
 *    what resolves a duplicate key.
 */
final class DictionaryCollectionTest extends TestCase
{
    /** @return Dictionary<int> A dictionary whose insertion order is deliberately not its sorted order. */
    private function dictionary(): Dictionary
    {
        return new Dictionary(["c" => 3, "a" => 1, "b" => 2]);
    }

    public function testSortingOrdersByValueWhileKeepingTheKeys(): void
    {
        $dictionary = $this->dictionary();

        $answer = $dictionary->sort(fn(int $lhs, int $rhs): int => $lhs <=> $rhs);

        $this->assertSame(["a" => 1, "b" => 2, "c" => 3], $dictionary->array, "each key stays with its own value");
        $this->assertSame($dictionary, $answer, "sort() is in place and answers the receiver");
    }

    public function testSortedCopiesRatherThanReordering(): void
    {
        $dictionary = $this->dictionary();

        $sorted = $dictionary->sorted([]);

        $this->assertInstanceOf(Dictionary::class, $sorted);
        $this->assertSame(["c" => 3, "a" => 1, "b" => 2], $dictionary->array, "the receiver keeps its order");
    }

    public function testMembershipAndExtremesReadTheValues(): void
    {
        $dictionary = $this->dictionary();

        $this->assertTrue($dictionary->containsElement(2), "a value is found regardless of its key");
        $this->assertFalse($dictionary->containsElement("b"), "a key is not a value");
        $this->assertFalse($dictionary->containsElement(99));
        $this->assertSame(1, $dictionary->min());
        $this->assertSame(3, $dictionary->max());
    }

    public function testTransformationsRunOverTheValues(): void
    {
        $dictionary = $this->dictionary();

        $this->assertSame([3, 1, 2], $dictionary->flatMap(fn(int $value): array => [$value])->array);
        $this->assertInstanceOf(FlattenSequence::class, $dictionary->joined());
    }

    public function testJoiningConcatenatesTheValues(): void
    {
        $this->assertSame("3-1-2", $this->dictionary()->join("-"));
        $this->assertSame("312", $this->dictionary()->join(""));
    }

    public function testSetDictionaryReplacesEveryEntry(): void
    {
        $dictionary = $this->dictionary();

        $dictionary->setDictionary(new Dictionary(["z" => 9]));

        $this->assertSame(["z" => 9], $dictionary->array);
    }

    public function testUniquingKeysBuildsFromAKeyedSequence(): void
    {
        $combined = Dictionary::uniquingKeys(new Dictionary(["a" => 1, "b" => 3]), fn(int $current, int $new): int => $current + $new);

        $this->assertSame(["a" => 1, "b" => 3], $combined->array, "distinct keys are carried across unchanged");
    }

    public function testTheClosureResolvesADuplicateKey(): void
    {
        $combined = Dictionary::uniquingKeys(new Dictionary(["a" => 1]), fn(int $current, int $new): int => $current + $new);

        $combined->merge(new Dictionary(["a" => 10, "b" => 2]), fn(int $current, int $new): int => $current + $new);

        $this->assertSame(["a" => 11, "b" => 2], $combined->array, "the colliding key is combined, the new one is added");
    }
}
