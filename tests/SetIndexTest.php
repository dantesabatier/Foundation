<?php

declare(strict_types=1);

namespace Sabatier\Foundation\Tests;

use Override;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use ReflectionProperty;
use Sabatier\Foundation\ArrayClass;
use Sabatier\Foundation\Hashable;
use Sabatier\Foundation\Number;
use Sabatier\Foundation\ObjectClass;
use Sabatier\Foundation\Range;
use Sabatier\Foundation\Set;
use Sabatier\Foundation\UUID;

final class RekeyableToken extends ObjectClass implements Hashable
{
    public string $code {
        set {
            $this->willChangeValueForKey(__PROPERTY__);
            $this->code = $value;
            $this->didChangeValueForKey(__PROPERTY__);
        }
    }
    #[Override]
    public string $hashValue {
        get => $this->code;
    }

    public function __construct(string $code)
    {
        $this->code = $code;
    }

    /** @return Set<string> */
    public static function keyPathsForValuesAffectingHashValue(): Set
    {
        return new Set(["code"]);
    }

    #[Override]
    public function isEqual(mixed $other): bool
    {
        return $other instanceof RekeyableToken && $other->code === $this->code;
    }
}

final class CountedToken extends ObjectClass implements Hashable
{
    public static int $comparisons = 0;
    #[Override]
    public string $hashValue {
        get => $this->code;
    }

    public function __construct(public readonly string $code)
    {
    }

    #[Override]
    public function isEqual(mixed $other): bool
    {
        self::$comparisons += 1;
        return $other instanceof CountedToken && $other->code === $this->code;
    }
}

/**
 * Tests the hash index Set searches through, against the element-by-element scan it
 * replaces.
 *
 * Regression guards:
 *  - the index answers exactly what the full scan answers: the same members, in the same
 *    order, the same instance kept, whatever the insertion order — including mixed scalars
 *    whose equality crosses types (1 and 1.0) and elements without a hash key (Number),
 *    which force the scan;
 *  - insert(null) on a set already holding null answers that nothing was inserted; the
 *    scan could not tell a matching null from no match and reported an insertion;
 *  - every mutation keeps the index in step with the storage: an element that leaves can
 *    be inserted again, one that is replaced is no longer found, and the wholesale
 *    rearrangements (sort, reverse, insertAt, removeAll, setSet) never leave a stale
 *    answer behind;
 *  - indexOf, and the update and remove built on it, locate an indexed member by the
 *    identity the bucket hands back instead of comparing it with every member again;
 *  - copies do not share an index: mutating a clone or an unserialized copy leaves the
 *    original's answers alone;
 *  - an element whose hash value changes while the set holds it is moved to its new key,
 *    and a set stops observing an element once it lets it go or is destroyed, so a
 *    long-lived element does not keep every set it was filed in alive.
 */
final class SetIndexTest extends TestCase
{
    /**
     * The members the element-by-element scan keeps: each element that is_equal() finds no earlier match for.
     * @param list<mixed> $elements
     * @return list<mixed>
     */
    private function scanned(array $elements): array
    {
        return new ArrayClass($elements)->reduce(new ArrayClass(), fn(ArrayClass $members, mixed $element) => $members->containsElement($element) ?: $members->append($element))->array;
    }

    /** @return iterable<string, array{list<mixed>}> */
    public static function elementsProvider(): iterable
    {
        $object = new ObjectClass();
        yield "mixed scalars" => [[1, "1", true, 1.0, null, 0, false, -0.0, 0.0, "a", "a", "", 2.5, 2]];
        yield "objects compared by identity" => [[$object, new ObjectClass(), $object, 1, $object]];
        yield "a Number, which has no hash key" => [[new Number(1), 1, true, 1.0, "1", 2]];
        yield "a Number equal to true but not to 0" => [[0, true, new Number(0.5), 0.5]];
    }

    /** @param list<mixed> $elements */
    #[DataProvider("elementsProvider")]
    public function testTheIndexKeepsWhatTheScanKeeps(array $elements): void
    {
        $this->assertSame($this->scanned($elements), new Set($elements)->array);

        $reversed = new ArrayClass($elements)->reversed()->array;
        $this->assertSame($this->scanned($reversed), new Set($reversed)->array, "in either insertion order");
    }

    /** @param list<mixed> $elements */
    #[DataProvider("elementsProvider")]
    public function testInsertingOneByOneKeepsWhatTheScanKeeps(array $elements): void
    {
        $set = new Set();
        foreach ($elements as $element) {
            $set->insert($element);
        }

        $this->assertSame($this->scanned($elements), $set->array);
    }

    public function testInsertAnswersTheMemberAlreadyStored(): void
    {
        $set = new Set([1]);

        $this->assertSame(["inserted" => false, "elementAfterInsert" => 1], $set->insert(1.0), "1.0 is equal to the stored 1, which is the one answered");
        $this->assertSame(["inserted" => true, "elementAfterInsert" => 2], $set->insert(2));
        $this->assertSame(1, $set->member(1.0));
        $this->assertNull($set->member(3));
    }

    public function testInsertingNullTwiceReportsTheSecondAsNotInserted(): void
    {
        $set = new Set([null]);

        // The scan finds its match through first(), which answers null both for "no match" and for a matching null, so it reported an insertion it did not make.
        $this->assertSame(["inserted" => false, "elementAfterInsert" => null], $set->insert(null));
        $this->assertSame(1, $set->count);
    }

    public function testUpdateReplacesTheMemberInTheIndexToo(): void
    {
        $set = new Set([1, 2]);

        $this->assertSame(2, $set->update(2.0), "the replaced member is answered");
        $this->assertSame([1, 2.0], $set->array);
        $this->assertSame(2.0, $set->member(2), "and the index finds the replacement");
        $this->assertNull($set->update(3));
        $this->assertTrue($set->containsElement(3));
    }

    public function testAnElementThatLeavesIsNoLongerFound(): void
    {
        $set = new Set([1, 2, 3, 4, 5]);

        $set->remove(2);
        $this->assertFalse($set->containsElement(2));
        $set->removeAt(0);
        $this->assertFalse($set->containsElement(1));
        $this->assertSame(3, $set->popFirst());
        $this->assertFalse($set->containsElement(3));
        unset($set[1]);
        $this->assertFalse($set->containsElement(5));

        $this->assertTrue($set->insert(2)["inserted"], "and can be inserted again");
        $this->assertTrue($set->insert(1)["inserted"]);
    }

    public function testAssigningOverAMemberReplacesItInTheIndex(): void
    {
        $set = new Set([1, 2]);
        $set->containsElement(1);

        $set[0] = 9;

        $this->assertFalse($set->containsElement(1));
        $this->assertTrue($set->containsElement(9));
    }

    public function testWholesaleRearrangementsLeaveNoStaleAnswer(): void
    {
        $set = new Set([3, 1, 2]);

        $set->sort();
        $this->assertFalse($set->insert(1.0)["inserted"], "sorted");
        $set->reverse();
        $this->assertFalse($set->insert(2.0)["inserted"], "reversed");
        $set->insertAt(5, 0);
        $this->assertTrue($set->containsElement(5), "inserted at a position");
        $set->insertAt(5, 1);
        $this->assertSame(4, $set->count, "and still unique");

        $set->removeAll(fn(int $element): bool => $element > 2);
        $this->assertFalse($set->containsElement(5), "removed by predicate");
        $this->assertFalse($set->containsElement(3));

        $set->setSet(new Set([7]));
        $this->assertFalse($set->containsElement(1), "replaced wholesale");
        $this->assertTrue($set->containsElement(7));
    }

    public function testAReorderedSetAnswersTheFirstMatchInItsNewOrder(): void
    {
        // Two distinct integers that the same float equals: past 2^53 PHP compares an int with a float as floats. Which one a search answers depends on storage order, so a reordering has to reach the index.
        $set = new Set([9007199254740993, 9007199254740992]);
        $this->assertSame(9007199254740993, $set->member(9007199254740992.0), "the first in storage order");

        $set->sort();

        $this->assertSame(9007199254740992, $set->member(9007199254740992.0), "the first in the sorted order, as the scan would answer");
    }

    public function testAnElementWithoutAHashKeyFallsBackToTheScan(): void
    {
        $set = new Set([1, 2]);
        $set->containsElement(1);

        $number = new Number(3);
        $set->insert($number);

        $this->assertSame(["inserted" => false, "elementAfterInsert" => $number], $set->insert(3), "the stored Number is equal to 3, and only the scan can tell");

        $set->remove($number);

        $this->assertTrue($set->insert(3)["inserted"], "once it leaves, 3 is new again");
    }

    public function testCopiesDoNotShareAnIndex(): void
    {
        $set = new Set([1, 2]);
        $set->containsElement(1);

        $copy = clone $set;
        $copy->remove(1);

        $this->assertTrue($set->containsElement(1), "removing from the clone leaves the original alone");
        $this->assertFalse($copy->containsElement(1));

        $restored = unserialize(serialize($set));
        $this->assertInstanceOf(Set::class, $restored);
        $this->assertTrue($restored->containsElement(2), "an unserialized copy rebuilds its index");
        $this->assertFalse($restored->insert(1.0)["inserted"]);
    }

    public function testEqualitySurvivesTheIndex(): void
    {
        $this->assertTrue(new Set([1, 2, 3])->isEqual(new Set([3.0, 2, 1])), "the same members in another order and spelling");
        $this->assertFalse(new Set([1, 2, 3])->isEqual(new Set([1, 2, "3"])));
        $this->assertTrue(new Set([1, 2])->isSubset(new Set([2, 1, 0])));
        $this->assertTrue(new Set([1, 2])->isDisjoint(new Set(["1", "2"])));
    }

    private static function observanceCount(ObjectClass $object): int
    {
        return count(new ReflectionProperty(ObjectClass::class, "observances")->getValue($object));
    }

    public function testAnElementWhoseHashValueChangesIsMovedToItsNewKey(): void
    {
        $token = new RekeyableToken("A");
        $set = new Set([$token, new RekeyableToken("B")]);
        $this->assertTrue($set->containsElement(new RekeyableToken("A")));

        $token->code = "C";

        $this->assertTrue($set->containsElement(new RekeyableToken("C")), "found under the key it now has");
        $this->assertFalse($set->containsElement(new RekeyableToken("A")), "and no longer under the one it had");
        $this->assertFalse($set->insert(new RekeyableToken("C"))["inserted"]);
        $set->remove(new RekeyableToken("C"));
        $this->assertFalse($set->containsElement($token));
        $this->assertSame(1, $set->count);
    }

    public function testACopyMovesItsOwnIndex(): void
    {
        $token = new RekeyableToken("A");
        $set = new Set([$token]);
        $set->containsElement($token);
        $copy = clone $set;
        $copy->containsElement($token);

        $token->code = "B";

        $this->assertTrue($set->containsElement(new RekeyableToken("B")));
        $this->assertTrue($copy->containsElement(new RekeyableToken("B")));
    }

    public function testASetStopsObservingAnElementItLetsGo(): void
    {
        $token = new RekeyableToken("A");
        $baseline = self::observanceCount($token);
        $set = new Set([$token]);
        $set->containsElement($token);
        $this->assertSame($baseline + 1, self::observanceCount($token), "filed, the element is observed");

        $set->remove($token);
        $this->assertSame($baseline, self::observanceCount($token), "removed, it no longer is");

        $set->insert($token);
        $set->removeAll();
        $this->assertSame($baseline, self::observanceCount($token), "nor after the index is discarded");

        $set = new Set([$token]);
        $set->containsElement($token);
        unset($set);
        $this->assertSame($baseline, self::observanceCount($token), "nor once the set is destroyed");
    }

    public function testAnElementWithAnImmutableHashValueIsNotObserved(): void
    {
        $uuid = new UUID();
        $set = new Set([$uuid]);
        $set->containsElement($uuid);

        $this->assertSame(0, self::observanceCount($uuid));
    }

    public function testLocatingAnIndexedElementDoesNotCompareItWithEveryMember(): void
    {
        $tokens = new ArrayClass(new Range(0, 200))->map(fn(int $i): CountedToken => new CountedToken("t$i"));
        $set = new Set($tokens->array);
        $set->containsElement(new CountedToken("t0"));
        CountedToken::$comparisons = 0;

        $this->assertSame(199, $set->indexOf(new CountedToken("t199")));
        $set->update(new CountedToken("t198"));
        $set->remove(new CountedToken("t197"));

        $this->assertLessThanOrEqual(3, CountedToken::$comparisons, "indexOf, update and remove each settle equality in the bucket");
        $this->assertSame(199, $set->count);
        $this->assertFalse($set->containsElement(new CountedToken("t197")));
    }
}
