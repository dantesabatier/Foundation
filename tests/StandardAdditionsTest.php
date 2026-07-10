<?php

declare(strict_types=1);

/**
 * Standalone regression tests for src/StandardAdditions.php.
 *
 * Run with: php tests/StandardAdditionsTest.php
 * Exits with a non-zero status code if any check fails.
 */

namespace Sabatier\Foundation\Tests;

use Sabatier\Foundation\Comparable;
use Sabatier\Foundation\CompareOptions;
use Sabatier\Foundation\ComparisonResult;
use Sabatier\Foundation\SearchMethod;

use function Sabatier\Foundation\array_remove;
use function Sabatier\Foundation\base64_url_encode;
use function Sabatier\Foundation\camelcase;
use function Sabatier\Foundation\compare;
use function Sabatier\Foundation\document_root_directory;
use function Sabatier\Foundation\in_range;
use function Sabatier\Foundation\is_equal;
use function Sabatier\Foundation\is_hidden;
use function Sabatier\Foundation\is_serialized;
use function Sabatier\Foundation\process_name;
use function Sabatier\Foundation\random_color;
use function Sabatier\Foundation\string_begins_with;
use function Sabatier\Foundation\string_compare;
use function Sabatier\Foundation\string_contains;
use function Sabatier\Foundation\string_ends_with;
use function Sabatier\Foundation\string_has_prefix;
use function Sabatier\Foundation\string_has_suffix;
use function Sabatier\Foundation\string_is_equal;
use function Sabatier\Foundation\string_matches;
use function Sabatier\Foundation\string_search;
use function Sabatier\Foundation\substring_from_index;
use function Sabatier\Foundation\substring_to_index;

require __DIR__ . "/../vendor/autoload.php";

final class TestRunner
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

/** Minimal Comparable used to exercise compare() and is_equal(). */
final class ComparableInt implements Comparable
{
    public function __construct(public readonly int $value)
    {
    }

    public function compare(mixed $other): ComparisonResult
    {
        $value = $other instanceof self ? $other->value : $other;
        return ComparisonResult::from($this->value <=> $value);
    }

    public function isEqual(mixed $other): bool
    {
        return $this->compare($other) === ComparisonResult::orderedSame;
    }
}

$check = TestRunner::check(...);
$section = TestRunner::section(...);

// ---------------------------------------------------------------------------
$section("string_compare");
// ---------------------------------------------------------------------------

$check(string_compare("a", "b") === -1, "plain ascending order");
$check(string_compare("b", "a") === 1, "plain descending order");
$check(string_compare("a", "a") === 0, "plain equal strings");
$check(string_compare("HOLA", "hola", CompareOptions::caseInsensitive) === 0, "case-insensitive equality");
$check(string_compare("árbol", "ARBOL", CompareOptions::caseInsensitive | CompareOptions::diacriticInsensitive) === 0, "case+diacritic-insensitive equality");
$check(string_compare("é", "e", CompareOptions::caseInsensitive) !== 0, "case-insensitive comparison still distinguishes diacritics");

// Every result must be clamped to the ComparisonResult range [-1, 1].
foreach ([["zebra", "apple", CompareOptions::none], ["apple", "zebra", CompareOptions::caseInsensitive], ["ñu", "nu", CompareOptions::diacriticInsensitive]] as [$a, $b, $options]) {
    $result = string_compare($a, $b, $options);
    $check($result >= -1 && $result <= 1, "result for ('$a', '$b') clamped to [-1, 1], got $result");
}

// Regression: the shared static Collator must not leak NORMALIZATION_MODE
// between calls. "a" + acute + ogonek (non-canonical order) vs the canonical
// order compare as different with normalization OFF but equal with ON, so a
// leak makes the result depend on which options previous calls used.
$nonCanonical = "a\u{0301}\u{0328}";
$canonical = "a\u{0328}\u{0301}";
$before = string_compare($nonCanonical, $canonical, CompareOptions::caseInsensitive);
$check($before !== 0, "caseInsensitive-only comparison must not normalize, even after earlier normalized calls");
string_compare("ä", "a", CompareOptions::caseInsensitive | CompareOptions::diacriticInsensitive);
$after = string_compare($nonCanonical, $canonical, CompareOptions::caseInsensitive);
$check($after === $before, "NORMALIZATION_MODE must not leak between calls (before=$before, after=$after)");

$check(string_is_equal("HOLA", "hola", CompareOptions::caseInsensitive), "string_is_equal case-insensitive");
$check(!string_is_equal("hola", "adios"), "string_is_equal different strings");

// ---------------------------------------------------------------------------
$section("string_search and derivatives");
// ---------------------------------------------------------------------------

// Regression: needles containing "/" used to produce a broken pattern because
// preg_quote() was called without the "/" delimiter.
$check(string_contains("ruta a/b lista", "a/b"), "needle containing a slash");
$check(string_begins_with("a/b/c", "a/b"), "prefix containing a slash");
$check(string_ends_with("a/b/c", "b/c"), "suffix containing a slash");
$check(string_matches("a/b", "a/b"), "exact match containing a slash");
$check(!string_contains("ruta axb lista", "a/b"), "slash needle does not act as a wildcard");

// Regression: an invalid raw pattern (quoted option) must return 0 and reset
// $matches instead of raising a TypeError on false.
set_error_handler(fn(): bool => true);
$matches = null;
$count = string_search("abc", "/[invalid/", SearchMethod::matches, CompareOptions::quoted, $matches);
restore_error_handler();
$check($count === 0, "invalid raw pattern returns 0, got $count");
$check($matches === [], "invalid raw pattern resets matches to an empty array");

$check(preg_quote(".+*?[^]$(){}=!<>|:-#/", "/") !== preg_quote(".+*?[^]$(){}=!<>|:-#/"), "sanity: delimiter argument changes preg_quote output");
$check(string_contains("precio (10.50) final", "(10.50)"), "needle with regex metacharacters is treated literally");
$check(!string_contains("precio 10X50 final", "(10.50)"), "dot in needle does not act as a wildcard");

$matches = null;
$check(string_search("uno dos uno tres uno", "uno", SearchMethod::contains, CompareOptions::none, $matches) === 3, "contains counts every occurrence");
$check($matches === ["uno", "uno", "uno"], "matches array holds the found substrings");

$check(string_matches("hola", "hola"), "matches: identical strings");
$check(!string_matches("hola mundo", "hola"), "matches: partial string is not a full match");
$check(string_begins_with("hola mundo", "hola"), "beginsWith: true case");
$check(!string_begins_with("hola mundo", "mundo"), "beginsWith: false case");
$check(string_ends_with("hola mundo", "mundo"), "endsWith: true case");
$check(!string_ends_with("hola mundo", "hola"), "endsWith: false case");
$check(string_contains("Hola Mundo", "mundo", CompareOptions::caseInsensitive), "contains: case-insensitive");
$check(string_contains("el gato negro", "gato", CompareOptions::words), "words option matches a whole word");
$check(!string_contains("los gatos negros", "gato", CompareOptions::words), "words option rejects a partial word");

// ---------------------------------------------------------------------------
$section("string_has_prefix / string_has_suffix");
// ---------------------------------------------------------------------------

$check(string_has_prefix("hola mundo", "hola"), "prefix present");
$check(!string_has_prefix("hola mundo", "mundo"), "prefix absent");
$check(string_has_suffix("hola mundo", "mundo"), "suffix present");
$check(!string_has_suffix("hola mundo", "hola"), "suffix absent");
$check(!string_has_suffix("bc", "abc"), "suffix longer than the string");

// ---------------------------------------------------------------------------
$section("substring helpers");
// ---------------------------------------------------------------------------

$check(substring_from_index("abcdef", 2) === "cdef", "from positive index");
$check(substring_from_index("abcdef", -2) === "ef", "from negative index");
$check(substring_from_index("abcdef", 0) === "abcdef", "from zero index");
$check(substring_to_index("abcdef", 3) === "abc", "to positive index");
$check(substring_to_index("abcdef", 0) === "", "to zero index");

// ---------------------------------------------------------------------------
$section("is_serialized");
// ---------------------------------------------------------------------------

// fdiv(0.0, 0.0) yields NAN at runtime; a literal NAN crashes Psalm 6.16.1
foreach ([true, false, 42, -7, 0, 0.5, INF, -INF, fdiv(0.0, 0.0), "hola", "", "con \"comillas\"", [1, 2], ["k" => "v"], null] as $original) {
    $serialized = serialize($original);
    $check(is_serialized($serialized), "accepts real serialize() output: $serialized");
}
foreach (["b:2;", "b:10;", "i:1.5;", "i:E+3;", "d:INFX;", "s:99:\"hi\";", "s:2:\"hi\"", "hola", "N;N;"] as $invalid) {
    $check(!is_serialized($invalid), "rejects invalid input: $invalid");
}
$check(!is_serialized(42), "rejects non-string values");

// ---------------------------------------------------------------------------
$section("array_remove");
// ---------------------------------------------------------------------------

$list = ["a", "b", "c"];
array_remove($list, "b");
$check($list === ["a", "c"], "list is re-indexed after removal");

// Regression: integer keys need not be consecutive for re-indexing to apply;
// is_sequential() (first key is an int), not array_is_list(), is the contract.
$gappy = [5 => "a", 9 => "b"];
array_remove($gappy, "a");
$check($gappy === [0 => "b"], "gappy integer-keyed array is re-indexed");

$mixed = [0 => "a", "x" => "b", 1 => "c"];
array_remove($mixed, "a");
$check($mixed === ["x" => "b", 1 => "c"], "no re-indexing when the remaining first key is a string");

$untouched = ["a", "b"];
array_remove($untouched, "z");
$check($untouched === ["a", "b"], "missing element leaves the array untouched");

// ---------------------------------------------------------------------------
$section("compare / is_equal");
// ---------------------------------------------------------------------------

$one = new ComparableInt(1);
$two = new ComparableInt(2);
$check(compare($one, $two) === -1, "both Comparable: ascending");
$check(compare($two, $one) === 1, "both Comparable: descending");
$check(compare($one, new ComparableInt(1)) === 0, "both Comparable: equal");
// Regression: when only one side implements Comparable the result must be
// consistent in both directions instead of falling back to <=>.
$check(compare($two, 1) === 1, "left-only Comparable");
$check(compare(1, $two) === -1, "right-only Comparable is negated");
$check(compare(1, $two) === -compare($two, 1), "asymmetric comparison is antisymmetric");
$check(compare(3, 7) === -1, "neither Comparable falls back to spaceship");

$check(is_equal($one, new ComparableInt(1)), "is_equal via Equatable");
$check(!is_equal($one, $two), "is_equal via Equatable: different");
$check(is_equal(1, 1.0), "int and float with the same value");
$check(!is_equal(1, "1"), "int and numeric string are not equal");
$check(is_equal("a", "a"), "identical strings");

// ---------------------------------------------------------------------------
$section("misc helpers");
// ---------------------------------------------------------------------------

$check(in_range(5, 1, 10), "value inside the range");
$check(in_range(1, 1, 10), "minimum is inclusive");
$check(!in_range(10, 1, 10), "maximum is exclusive");
$check(!in_range(5, 10, 1), "inverted bounds are always false");

$check(camelcase("hello world") === "helloWorld", "camelcase with spaces");
$check(camelcase("hello_world") === "helloWorld", "camelcase with underscores");
$check(camelcase("hello-world-foo") === "helloWorldFoo", "camelcase with hyphens");

$check(base64_url_encode("hello?>") === "aGVsbG8_Pg", "base64 url-safe encoding without padding");

$color = random_color("hello");
$check((bool)preg_match("/^#[0-9A-F]{6}$/", $color), "random_color returns a hex color, got $color");
$check($color === random_color("hello"), "random_color is deterministic");

$check(is_hidden(".hidden-nonexistent-file"), "dotfile fallback reports hidden (short shell output guard)");
$check(!is_hidden(__FILE__), "this test file is not hidden");

$check(process_name() !== "", "process_name returns a non-empty string");
$check(is_string(document_root_directory()), "document_root_directory returns a string");

TestRunner::finish();
