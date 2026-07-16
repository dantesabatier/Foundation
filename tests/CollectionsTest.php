<?php

declare(strict_types=1);

namespace Sabatier\Foundation\Tests;

use PHPUnit\Framework\TestCase;
use Sabatier\Foundation\ArrayClass;
use Sabatier\Foundation\Dictionary;
use Sabatier\Foundation\Set;
use Sabatier\Foundation\Slice;

/**
 * Tests for src/ArrayClass.php, src/Dictionary.php, src/Set.php, and src/Slice.php.
 *
 * Regression guards:
 *  - Slice::$array passed the end index as array_slice()'s length argument, so any slice
 *    that did not start at index 0 leaked elements past its upper bound (split() returned
 *    oversized subsequences);
 *  - ArrayClass::split(), Dictionary::compactMap(), and Slice::filter() now build their
 *    results with append() and must keep their exact semantics;
 *  - Set must accept any iterable in its constructor (URLCache builds one from
 *    Dictionary->keys);
 *  - ArrayClass::dropFirst() built an inverted Range and threw when the drop count
 *    exceeded the element count; it now clamps the count and returns an empty subsequence,
 *    honouring its documented contract (dropLast already clamped its bound).
 */
final class CollectionsTest extends TestCase
{
    public function testArrayClassBasics(): void
    {
        $numbers = new ArrayClass([3, 1, 2]);
        $this->assertSame(3, $numbers->count, "count");
        $this->assertFalse($numbers->isEmpty, "isEmpty on populated array");
        $this->assertTrue(new ArrayClass()->isEmpty, "isEmpty on empty array");
        $this->assertSame(3, $numbers->first, "first");
        $this->assertSame(2, $numbers->last, "last");
        $this->assertSame(1, $numbers->indexOf(1), "indexOf");
        $this->assertNull($numbers->indexOf(99), "indexOf missing element");
        $this->assertTrue($numbers->containsElement(2), "containsElement");
        $this->assertTrue($numbers->contains(fn(int $e): bool => $e > 2), "contains with predicate");
        $this->assertSame(1, $numbers->min(), "min and max");
        $this->assertSame(3, $numbers->max(), "min and max");
        $this->assertSame("3,1,2", $numbers->join(","), "join");
    }

    public function testArrayClassMutation(): void
    {
        $mutable = new ArrayClass([1, 2]);
        $mutable->append(3);
        $this->assertSame([1, 2, 3], $mutable->array, "append mutates in place");
        $appended = $mutable->appending(4);
        $this->assertSame([1, 2, 3, 4], $appended->array, "appending does not mutate the receiver");
        $this->assertSame([1, 2, 3], $mutable->array, "appending does not mutate the receiver");
        $mutable->appendContentsOf([4, 5]);
        $this->assertSame([1, 2, 3, 4, 5], $mutable->array, "appendContentsOf");
        $mutable->insertAt(0, 0);
        $this->assertSame([0, 1, 2, 3, 4, 5], $mutable->array, "insertAt");
        $mutable->removeAt(0);
        $this->assertSame([1, 2, 3, 4, 5], $mutable->array, "removeAt");
        $this->assertSame(5, $mutable->popLast(), "popLast returns and removes");
        $this->assertSame([1, 2, 3, 4], $mutable->array, "popLast returns and removes");
        $this->assertSame(1, $mutable->popFirst(), "popFirst returns and removes");
        $this->assertSame([2, 3, 4], $mutable->array, "popFirst returns and removes");
        $this->assertNull(new ArrayClass()->popLast(), "popLast on empty array is null");
        $this->assertNull(new ArrayClass()->popFirst(), "popFirst on empty array is null");
    }

    public function testArrayClassSortAndReverse(): void
    {
        $this->assertSame([1, 2, 3], new ArrayClass([3, 1, 2])->sort()->array, "sort ascending");
        $this->assertSame([3, 2, 1], new ArrayClass([1, 2, 3])->reversed()->array, "reversed");
        $original = new ArrayClass([1, 2, 3]);
        $original->reversed();
        $this->assertSame([1, 2, 3], $original->array, "reversed does not mutate the receiver");
    }

    public function testArrayClassFunctionalAlgorithms(): void
    {
        $source = new ArrayClass([1, 2, 3, 4]);
        $this->assertSame([2, 4, 6, 8], $source->map(fn(int $e): int => $e * 2)->array, "map");
        $this->assertSame([2, 4], $source->filter(fn(int $e): bool => $e % 2 === 0)->array, "filter");
        $this->assertSame([20, 40], $source->compactMap(fn(int $e): ?int => $e % 2 === 0 ? $e * 10 : null)->array, "compactMap drops nulls");
        $this->assertSame([1, 1, 2, 2, 3, 3, 4, 4], $source->flatMap(fn(int $e): array => [$e, $e])->array, "flatMap");
        // reduce follows Swift's reduce(into:): the closure mutates the accumulator by reference.
        $this->assertSame(10, $source->reduce(0, fn(int &$acc, int $e): int => $acc += $e), "reduce mutates the accumulator by reference");
        $this->assertTrue($source->allSatisfy(fn(int $e): bool => $e > 0), "allSatisfy true");
        $this->assertFalse($source->allSatisfy(fn(int $e): bool => $e > 1), "allSatisfy false");
        $this->assertSame(3, $source->first(fn(int $e): bool => $e > 2), "first with predicate");
        $this->assertSame(2, $source->firstIndex(fn(int $e): bool => $e > 2), "firstIndex");
        $this->assertSame(2, $source->lastIndex(fn(int $e): bool => $e < 4), "lastIndex");
    }

    public function testArrayClassFilterStopsEarly(): void
    {
        // The filter closure can stop the iteration through its by-ref third parameter.
        $source = new ArrayClass([1, 2, 3, 4]);
        $visited = [];
        $stopped = $source->filter(function (int $e, int $i, bool &$stop) use (&$visited): bool {
            $visited[] = $e;
            $stop = $e === 2;
            return true;
        });
        $this->assertSame([1, 2], $visited, "filter stops early when the closure sets stop");
        $this->assertSame([1, 2], $stopped->array, "filter keeps the elements accepted before stopping");
    }

    public function testSplitAndSeparate(): void
    {
        $sequence = new ArrayClass([1, 0, 2, 3, 0, 0, 4]);
        $slices = $sequence->split(fn(int $e): bool => $e === 0);
        $this->assertSame(3, $slices->count, "split produces one slice per non-empty subsequence");
        // A middle slice used to leak elements past its upper bound because Slice::$array
        // passed the end index as array_slice()'s length.
        $this->assertSame([[1], [2, 3], [4]], $slices->map(fn(Slice $s): array => $s->array)->array, "split slices contain exactly their subsequence");
        $this->assertSame(4, $sequence->split(fn(int $e): bool => $e === 0, omittingEmptySubsequences: false)->count, "split keeps empty subsequences on demand");
        $this->assertSame(2, $sequence->split(fn(int $e): bool => $e === 0, 1)->count, "split honors maxSplits");
        $this->assertSame([[1], [2, 3], [4]], $sequence->separate(0)->map(fn(Slice $s): array => $s->array)->array, "separate splits on an element");
        $this->assertSame([[1]], new ArrayClass([0, 1, 0])->split(fn(int $e): bool => $e === 0)->map(fn(Slice $s): array => $s->array)->array, "split ignores leading and trailing separators");
    }

    public function testSliceBounds(): void
    {
        $sequence = new ArrayClass([1, 0, 2, 3, 0, 0, 4]);
        $slices = $sequence->split(fn(int $e): bool => $e === 0);
        $middle = $slices[1];
        $this->assertSame(2, $middle->count, "slice count comes from its bounds");
        $this->assertSame(2, $middle->startIndex, "slice start and end indices");
        $this->assertSame(4, $middle->endIndex, "slice start and end indices");
        $this->assertSame(2, $middle->first, "slice first");
        $this->assertSame([2 => 2, 3 => 3], iterator_to_array($middle), "slice iteration preserves the base collection's indices");
        $this->assertSame([3], $middle->filter(fn(int $e): bool => $e > 2)->array, "slice filter");
        $this->assertSame("[2,3]", json_encode($middle), "slice jsonSerialize uses its own bounds");
    }

    public function testPrefixSuffixAndDrop(): void
    {
        $letters = new ArrayClass(["a", "b", "c", "d"]);
        $this->assertSame(["a", "b"], $letters->prefix(2)->array, "prefix");
        $this->assertSame(["c", "d"], $letters->suffix(2)->array, "suffix");
        $this->assertSame(["b", "c", "d"], $letters->dropFirst(1)->array, "dropFirst");
        $this->assertSame(["a", "b", "c"], $letters->dropLast(1)->array, "dropLast");
        $this->assertSame(["c", "d"], $letters->drop(fn(string $e): bool => $e < "c")->array, "drop while");

        // dropFirst/dropLast must clamp the drop count to the element count instead of
        // building an inverted Range: k greater than or equal to the count yields an empty
        // subsequence, and k of zero drops nothing.
        $this->assertSame(["a", "b", "c", "d"], $letters->dropFirst(0)->array, "dropFirst(0) drops nothing");
        $this->assertSame([], $letters->dropFirst($letters->count)->array, "dropFirst(count) is empty");
        $this->assertSame([], $letters->dropFirst($letters->count + 1)->array, "dropFirst beyond count is empty");
        $this->assertSame(["a", "b", "c", "d"], $letters->dropLast(0)->array, "dropLast(0) drops nothing");
        $this->assertSame([], $letters->dropLast($letters->count)->array, "dropLast(count) is empty");
        $this->assertSame([], $letters->dropLast($letters->count + 1)->array, "dropLast beyond count is empty");
    }

    public function testDictionaryBasics(): void
    {
        $dictionary = new Dictionary(["a" => 1, "b" => 2, "c" => 3]);
        $this->assertSame(3, $dictionary->count, "count");
        $this->assertSame(["a", "b", "c"], $dictionary->keys->array, "keys");
        $this->assertSame([1, 2, 3], $dictionary->values->array, "values");
        $this->assertSame(2, $dictionary["b"], "subscript read");
        $this->assertNull($dictionary["missing"], "subscript read of a missing key is null");
        $this->assertSame(3, $dictionary->valueForKey("c"), "valueForKey");
    }

    public function testDictionaryMutation(): void
    {
        $mutableDictionary = new Dictionary(["a" => 1]);
        $mutableDictionary["b"] = 2;
        $this->assertSame(2, $mutableDictionary->count, "subscript write");
        $this->assertSame(2, $mutableDictionary["b"], "subscript write");
        $this->assertSame(1, $mutableDictionary->updateValue(10, "a"), "updateValue returns the previous value");
        $this->assertSame(10, $mutableDictionary["a"], "updateValue stores the new value");
        $this->assertSame(10, $mutableDictionary->removeValueForKey("a"), "removeValueForKey returns the removed value");
        $this->assertSame(1, $mutableDictionary->count, "removeValueForKey removes the entry");
    }

    public function testDictionaryFunctionalAlgorithms(): void
    {
        $dictionary = new Dictionary(["a" => 1, "b" => 2, "c" => 3]);
        $this->assertSame(["b", "c"], $dictionary->filter(fn(int $value): bool => $value > 1)->keys->array, "filter keeps matching entries with their keys");
        $this->assertSame(["a1", "b2", "c3"], $dictionary->map(fn(int $value, string $key): string => "$key$value")->array, "map receives value and key");
        // Dictionary::compactMap builds an ArrayClass with append() and must drop nulls.
        $this->assertSame([10, 30], $dictionary->compactMap(fn(int $value): ?int => $value % 2 === 1 ? $value * 10 : null)->array, "compactMap drops nulls");
        $this->assertSame(["a" => 2, "b" => 4, "c" => 6], $dictionary->mapValues(fn(int $value): int => $value * 2)->array, "mapValues keeps keys");
        $this->assertSame(["b" => 2, "c" => 3], $dictionary->compactMapValues(fn(int $value): ?int => $value > 1 ? $value : null)->array, "compactMapValues keeps keys and drops nulls");
        $this->assertSame(6, $dictionary->reduce(0, fn(int &$acc, int $value): int => $acc += $value), "reduce over values");
    }

    public function testDictionaryNumericStringKeys(): void
    {
        // PHP converts numeric-string array keys to int internally; the key-returning methods
        // must still honor their declared string contract (TaskRegistry keys tasks by number).
        $numericKeys = new Dictionary(["7" => "a", "9" => "b"]);
        $this->assertSame("9", $numericKeys->firstIndex(fn(string $value): bool => $value === "b"), "firstIndex returns a string key even when PHP intified it");
        $this->assertSame("7", $numericKeys->indexOf("a"), "indexOf returns a string key even when PHP intified it");
    }

    public function testDictionaryGroupingMergingAndEquality(): void
    {
        $grouped = Dictionary::grouping(new ArrayClass(["ana", "aldo", "beto"]), fn(string $name): string => $name[0]);
        $this->assertSame(2, $grouped->count, "grouping produces one entry per key");
        $this->assertSame(["ana", "aldo"], $grouped["a"]->array, "grouping preserves element order inside groups");

        $merged = new Dictionary(["a" => 1, "b" => 2])->merging(["b" => 20, "c" => 3], fn(int $current, int $new): int => $new);
        $this->assertSame(["a" => 1, "b" => 20, "c" => 3], $merged->array, "merging resolves duplicates with the combine closure");
        $this->assertTrue(new Dictionary(["a" => 1])->isEqual(new Dictionary(["a" => 1])), "isEqual on equal dictionaries");
        $this->assertFalse(new Dictionary(["a" => 1])->isEqual(new Dictionary(["a" => 2])), "isEqual on different dictionaries");
    }

    public function testSetBasics(): void
    {
        $set = new Set([1, 1, 2, 3, 3]);
        $this->assertSame(3, $set->count, "constructor deduplicates");
        $this->assertTrue($set->containsElement(2), "containsElement");
        $this->assertSame(2, $set->member(2), "member returns the stored element");
        $this->assertNull($set->member(99), "member of a missing element is null");
    }

    public function testSetInsertAndRemove(): void
    {
        $set = new Set([1, 1, 2, 3, 3]);
        $insertion = $set->insert(4);
        $this->assertTrue($insertion["inserted"], "insert of a new element");
        $this->assertSame(4, $set->count, "insert of a new element");
        $duplicate = $set->insert(4);
        $this->assertFalse($duplicate["inserted"], "insert of a duplicate is rejected");
        $this->assertSame(4, $set->count, "insert of a duplicate is rejected");
        $this->assertSame(4, $duplicate["elementAfterInsert"], "insert of a duplicate returns the existing element");
        $set->remove(4);
        $this->assertSame(3, $set->count, "remove");
        $this->assertFalse($set->containsElement(4), "remove");
    }

    public function testSetConstructionFromIterable(): void
    {
        // URLCache builds a Set from Dictionary->keys: the constructor must accept any iterable.
        $fromKeys = new Set(new Dictionary(["x" => 1, "y" => 2])->keys);
        $this->assertSame(2, $fromKeys->count, "construction from Dictionary keys");
        $this->assertTrue($fromKeys->containsElement("x"), "construction from Dictionary keys");
        $this->assertTrue($fromKeys->containsElement("y"), "construction from Dictionary keys");
    }

    public function testSetMapAndFilter(): void
    {
        $set = new Set([1, 2, 3]);
        $this->assertSame(2, $set->map(fn(int $e): int => $e % 2)->count, "map deduplicates its results");
        $this->assertSame(2, $set->filter(fn(int $e): bool => $e > 1)->count, "filter returns a Set");
    }
}
