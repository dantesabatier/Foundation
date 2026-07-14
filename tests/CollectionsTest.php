<?php

declare(strict_types=1);

/**
 * Standalone tests for the core collection types: ArrayClass, Dictionary, Set, and Slice.
 *
 * Run with: php tests/CollectionsTest.php
 * Exits with a non-zero status code if any check fails.
 *
 * Regression guards:
 *  - Slice::$array passed the end index as array_slice()'s length argument, so any slice
 *    that did not start at index 0 leaked elements past its upper bound (split() returned
 *    oversized subsequences);
 *  - ArrayClass::split(), Dictionary::compactMap(), and Slice::filter() now build their
 *    results with append() and must keep their exact semantics;
 *  - Set must accept any iterable in its constructor (URLCache builds one from
 *    Dictionary->keys).
 */

namespace Sabatier\Foundation\Tests;

use Sabatier\Foundation\ArrayClass;
use Sabatier\Foundation\Dictionary;
use Sabatier\Foundation\Set;
use Sabatier\Foundation\Slice;

require __DIR__ . "/../vendor/autoload.php";

final class CollectionsTestRunner
{
    public static int $passed = 0;
    /** @var list<string> */
    public static array $failures = [];
    private static string $section = "";

    public static function section(string $name): void
    {
        self::$section = $name;
    }

    public static function check(bool $condition, string $message): void
    {
        if ($condition) {
            self::$passed++;
            return;
        }
        $failure = self::$section === "" ? $message : self::$section . ": " . $message;
        self::$failures[] = $failure;
        fwrite(STDERR, "FAIL $failure" . PHP_EOL);
    }

    public static function finish(): never
    {
        $failed = count(self::$failures);
        printf("%d passed, %d failed%s", self::$passed, $failed, PHP_EOL);
        exit($failed > 0 ? 1 : 0);
    }
}

/** Fails the process on any PHP warning/notice. */
set_error_handler(function (int $severity, string $message, string $file, int $line): bool {
    throw new \ErrorException($message, 0, $severity, $file, $line);
});

$check = CollectionsTestRunner::check(...);
$section = CollectionsTestRunner::section(...);

// ---------------------------------------------------------------------------
$section("ArrayClass basics");
// ---------------------------------------------------------------------------

$numbers = new ArrayClass([3, 1, 2]);
$check($numbers->count === 3, "count");
$check($numbers->isEmpty === false, "isEmpty on populated array");
$check(new ArrayClass()->isEmpty === true, "isEmpty on empty array");
$check($numbers->first === 3, "first");
$check($numbers->last === 2, "last");
$check($numbers->indexOf(1) === 1, "indexOf");
$check($numbers->indexOf(99) === null, "indexOf missing element");
$check($numbers->containsElement(2) === true, "containsElement");
$check($numbers->contains(fn(int $e): bool => $e > 2) === true, "contains with predicate");
$check($numbers->min() === 1 && $numbers->max() === 3, "min and max");
$check($numbers->join(",") === "3,1,2", "join");

$mutable = new ArrayClass([1, 2]);
$mutable->append(3);
$check($mutable->array === [1, 2, 3], "append mutates in place");
$appended = $mutable->appending(4);
$check($appended->array === [1, 2, 3, 4] && $mutable->array === [1, 2, 3], "appending does not mutate the receiver");
$mutable->appendContentsOf([4, 5]);
$check($mutable->array === [1, 2, 3, 4, 5], "appendContentsOf");
$mutable->insertAt(0, 0);
$check($mutable->array === [0, 1, 2, 3, 4, 5], "insertAt");
$mutable->removeAt(0);
$check($mutable->array === [1, 2, 3, 4, 5], "removeAt");
$check($mutable->popLast() === 5 && $mutable->array === [1, 2, 3, 4], "popLast returns and removes");
$check($mutable->popFirst() === 1 && $mutable->array === [2, 3, 4], "popFirst returns and removes");
$check(new ArrayClass()->popLast() === null, "popLast on empty array is null");
$check(new ArrayClass()->popFirst() === null, "popFirst on empty array is null");

$check(new ArrayClass([3, 1, 2])->sort()->array === [1, 2, 3], "sort ascending");
$check(new ArrayClass([1, 2, 3])->reversed()->array === [3, 2, 1], "reversed");
$original = new ArrayClass([1, 2, 3]);
$original->reversed();
$check($original->array === [1, 2, 3], "reversed does not mutate the receiver");

// ---------------------------------------------------------------------------
$section("ArrayClass functional algorithms");
// ---------------------------------------------------------------------------

$source = new ArrayClass([1, 2, 3, 4]);
$check($source->map(fn(int $e): int => $e * 2)->array === [2, 4, 6, 8], "map");
$check($source->filter(fn(int $e): bool => $e % 2 === 0)->array === [2, 4], "filter");
$check($source->compactMap(fn(int $e): ?int => $e % 2 === 0 ? $e * 10 : null)->array === [20, 40], "compactMap drops nulls");
$check($source->flatMap(fn(int $e): array => [$e, $e])->array === [1, 1, 2, 2, 3, 3, 4, 4], "flatMap");
// reduce follows Swift's reduce(into:): the closure mutates the accumulator by reference.
$check($source->reduce(0, fn(int &$acc, int $e): int => $acc += $e) === 10, "reduce mutates the accumulator by reference");
$check($source->allSatisfy(fn(int $e): bool => $e > 0) === true, "allSatisfy true");
$check($source->allSatisfy(fn(int $e): bool => $e > 1) === false, "allSatisfy false");
$check($source->first(fn(int $e): bool => $e > 2) === 3, "first with predicate");
$check($source->firstIndex(fn(int $e): bool => $e > 2) === 2, "firstIndex");
$check($source->lastIndex(fn(int $e): bool => $e < 4) === 2, "lastIndex");

// The filter closure can stop the iteration through its by-ref third parameter.
$visited = [];
$stopped = $source->filter(function (int $e, int $i, bool &$stop) use (&$visited): bool {
    $visited[] = $e;
    $stop = $e === 2;
    return true;
});
$check($visited === [1, 2], "filter stops early when the closure sets stop");
$check($stopped->array === [1, 2], "filter keeps the elements accepted before stopping");

// ---------------------------------------------------------------------------
$section("split / separate and Slice bounds");
// ---------------------------------------------------------------------------

$sequence = new ArrayClass([1, 0, 2, 3, 0, 0, 4]);
$slices = $sequence->split(fn(int $e): bool => $e === 0);
$check($slices->count === 3, "split produces one slice per non-empty subsequence");
// A middle slice used to leak elements past its upper bound because Slice::$array
// passed the end index as array_slice()'s length.
$check($slices->map(fn(Slice $s): array => $s->array)->array === [[1], [2, 3], [4]], "split slices contain exactly their subsequence");
$check($sequence->split(fn(int $e): bool => $e === 0, omittingEmptySubsequences: false)->count === 4, "split keeps empty subsequences on demand");
$check($sequence->split(fn(int $e): bool => $e === 0, 1)->count === 2, "split honors maxSplits");
$check($sequence->separate(0)->map(fn(Slice $s): array => $s->array)->array === [[1], [2, 3], [4]], "separate splits on an element");
$check(new ArrayClass([0, 1, 0])->split(fn(int $e): bool => $e === 0)->map(fn(Slice $s): array => $s->array)->array === [[1]], "split ignores leading and trailing separators");

$middle = $slices[1];
$check($middle->count === 2, "slice count comes from its bounds");
$check($middle->startIndex === 2 && $middle->endIndex === 4, "slice start and end indices");
$check($middle->first === 2, "slice first");
$check(iterator_to_array($middle) === [2 => 2, 3 => 3], "slice iteration preserves the base collection's indices");
$check($middle->filter(fn(int $e): bool => $e > 2)->array === [3], "slice filter");
$check(json_encode($middle) === "[2,3]", "slice jsonSerialize uses its own bounds");

$letters = new ArrayClass(["a", "b", "c", "d"]);
$check($letters->prefix(2)->array === ["a", "b"], "prefix");
$check($letters->suffix(2)->array === ["c", "d"], "suffix");
$check($letters->dropFirst(1)->array === ["b", "c", "d"], "dropFirst");
$check($letters->dropLast(1)->array === ["a", "b", "c"], "dropLast");
$check($letters->drop(fn(string $e): bool => $e < "c")->array === ["c", "d"], "drop while");

// ---------------------------------------------------------------------------
$section("Dictionary");
// ---------------------------------------------------------------------------

$dictionary = new Dictionary(["a" => 1, "b" => 2, "c" => 3]);
$check($dictionary->count === 3, "count");
$check($dictionary->keys->array === ["a", "b", "c"], "keys");
$check($dictionary->values->array === [1, 2, 3], "values");
$check($dictionary["b"] === 2, "subscript read");
$check($dictionary["missing"] === null, "subscript read of a missing key is null");
$check($dictionary->valueForKey("c") === 3, "valueForKey");

$mutableDictionary = new Dictionary(["a" => 1]);
$mutableDictionary["b"] = 2;
$check($mutableDictionary->count === 2 && $mutableDictionary["b"] === 2, "subscript write");
$check($mutableDictionary->updateValue(10, "a") === 1, "updateValue returns the previous value");
$check($mutableDictionary["a"] === 10, "updateValue stores the new value");
$check($mutableDictionary->removeValueForKey("a") === 10, "removeValueForKey returns the removed value");
$check($mutableDictionary->count === 1, "removeValueForKey removes the entry");

$check($dictionary->filter(fn(int $value): bool => $value > 1)->keys->array === ["b", "c"], "filter keeps matching entries with their keys");
$check($dictionary->map(fn(int $value, string $key): string => "$key$value")->array === ["a1", "b2", "c3"], "map receives value and key");
// Dictionary::compactMap builds an ArrayClass with append() and must drop nulls.
$check($dictionary->compactMap(fn(int $value): ?int => $value % 2 === 1 ? $value * 10 : null)->array === [10, 30], "compactMap drops nulls");
$check($dictionary->mapValues(fn(int $value): int => $value * 2)->array === ["a" => 2, "b" => 4, "c" => 6], "mapValues keeps keys");
$check($dictionary->compactMapValues(fn(int $value): ?int => $value > 1 ? $value : null)->array === ["b" => 2, "c" => 3], "compactMapValues keeps keys and drops nulls");
$check($dictionary->reduce(0, fn(int &$acc, int $value): int => $acc += $value) === 6, "reduce over values");

// PHP converts numeric-string array keys to int internally; the key-returning methods
// must still honor their declared string contract (TaskRegistry keys tasks by number).
$numericKeys = new Dictionary(["7" => "a", "9" => "b"]);
$check($numericKeys->firstIndex(fn(string $value): bool => $value === "b") === "9", "firstIndex returns a string key even when PHP intified it");
$check($numericKeys->indexOf("a") === "7", "indexOf returns a string key even when PHP intified it");

$grouped = Dictionary::grouping(new ArrayClass(["ana", "aldo", "beto"]), fn(string $name): string => $name[0]);
$check($grouped->count === 2, "grouping produces one entry per key");
$check($grouped["a"]->array === ["ana", "aldo"], "grouping preserves element order inside groups");

$merged = new Dictionary(["a" => 1, "b" => 2])->merging(["b" => 20, "c" => 3], fn(int $current, int $new): int => $new);
$check($merged->array === ["a" => 1, "b" => 20, "c" => 3], "merging resolves duplicates with the combine closure");
$check(new Dictionary(["a" => 1])->isEqual(new Dictionary(["a" => 1])), "isEqual on equal dictionaries");
$check(!new Dictionary(["a" => 1])->isEqual(new Dictionary(["a" => 2])), "isEqual on different dictionaries");

// ---------------------------------------------------------------------------
$section("Set");
// ---------------------------------------------------------------------------

$set = new Set([1, 1, 2, 3, 3]);
$check($set->count === 3, "constructor deduplicates");
$check($set->containsElement(2) === true, "containsElement");
$check($set->member(2) === 2, "member returns the stored element");
$check($set->member(99) === null, "member of a missing element is null");

$insertion = $set->insert(4);
$check($insertion["inserted"] === true && $set->count === 4, "insert of a new element");
$duplicate = $set->insert(4);
$check($duplicate["inserted"] === false && $set->count === 4, "insert of a duplicate is rejected");
$check($duplicate["elementAfterInsert"] === 4, "insert of a duplicate returns the existing element");
$set->remove(4);
$check($set->count === 3 && !$set->containsElement(4), "remove");

// URLCache builds a Set from Dictionary->keys: the constructor must accept any iterable.
$fromKeys = new Set(new Dictionary(["x" => 1, "y" => 2])->keys);
$check($fromKeys->count === 2 && $fromKeys->containsElement("x") && $fromKeys->containsElement("y"), "construction from Dictionary keys");

$check($set->map(fn(int $e): int => $e % 2)->count === 2, "map deduplicates its results");
$check($set->filter(fn(int $e): bool => $e > 1)->count === 2, "filter returns a Set");

CollectionsTestRunner::finish();
