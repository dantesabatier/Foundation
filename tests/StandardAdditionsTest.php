<?php

declare(strict_types=1);

namespace Sabatier\Foundation\Tests;

use Override;
use PHPUnit\Framework\TestCase;
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
use function Sabatier\Foundation\is_directory_junction;
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

/** Minimal Comparable used to exercise compare() and is_equal(). */
final class ComparableInt implements Comparable
{
    public function __construct(public readonly int $value)
    {
    }

    #[Override]
    public function compare(mixed $other): ComparisonResult
    {
        $value = $other instanceof self ? $other->value : $other;
        return ComparisonResult::from($this->value <=> $value);
    }

    #[Override]
    public function isEqual(mixed $other): bool
    {
        return $this->compare($other) === ComparisonResult::orderedSame;
    }
}

/**
 * Tests for src/StandardAdditions.php.
 *
 * Regression guards:
 *  - the shared static Collator must not leak NORMALIZATION_MODE between
 *    string_compare() calls;
 *  - string_search() needles containing "/" used to produce a broken pattern
 *    because preg_quote() was called without the "/" delimiter;
 *  - an invalid raw pattern (quoted option) must return 0 and reset $matches
 *    instead of raising a TypeError on false;
 *  - array_remove() re-indexes through is_sequential() (first key is an int),
 *    not array_is_list();
 *  - compare() must be antisymmetric when only one side implements Comparable.
 */
final class StandardAdditionsTest extends TestCase
{
    public function testStringCompare(): void
    {
        $this->assertSame(-1, string_compare("a", "b"), "plain ascending order");
        $this->assertSame(1, string_compare("b", "a"), "plain descending order");
        $this->assertSame(0, string_compare("a", "a"), "plain equal strings");
        $this->assertSame(0, string_compare("HOLA", "hola", CompareOptions::caseInsensitive), "case-insensitive equality");
        $this->assertSame(0, string_compare("árbol", "ARBOL", CompareOptions::caseInsensitive | CompareOptions::diacriticInsensitive), "case+diacritic-insensitive equality");
        $this->assertNotSame(0, string_compare("é", "e", CompareOptions::caseInsensitive), "case-insensitive comparison still distinguishes diacritics");
    }

    public function testStringCompareResultsAreClamped(): void
    {
        // Every result must be clamped to the ComparisonResult range [-1, 1].
        foreach ([["zebra", "apple", CompareOptions::none], ["apple", "zebra", CompareOptions::caseInsensitive], ["ñu", "nu", CompareOptions::diacriticInsensitive]] as [$a, $b, $options]) {
            $result = string_compare($a, $b, $options);
            $this->assertGreaterThanOrEqual(-1, $result, "result for ('$a', '$b') clamped to [-1, 1], got $result");
            $this->assertLessThanOrEqual(1, $result, "result for ('$a', '$b') clamped to [-1, 1], got $result");
        }
    }

    public function testStringCompareNormalizationModeDoesNotLeak(): void
    {
        // Regression: the shared static Collator must not leak NORMALIZATION_MODE
        // between calls. "a" + acute + ogonek (non-canonical order) vs the canonical
        // order compare as different with normalization OFF but equal with ON, so a
        // leak makes the result depend on which options previous calls used.
        $nonCanonical = "a\u{0301}\u{0328}";
        $canonical = "a\u{0328}\u{0301}";
        $before = string_compare($nonCanonical, $canonical, CompareOptions::caseInsensitive);
        $this->assertNotSame(0, $before, "caseInsensitive-only comparison must not normalize, even after earlier normalized calls");
        string_compare("ä", "a", CompareOptions::caseInsensitive | CompareOptions::diacriticInsensitive);
        $after = string_compare($nonCanonical, $canonical, CompareOptions::caseInsensitive);
        $this->assertSame($before, $after, "NORMALIZATION_MODE must not leak between calls (before=$before, after=$after)");
    }

    public function testStringCompareInvalidUTF8FallsBackToBytes(): void
    {
        $this->assertSame(-1, string_compare("ma\xF1ana", "NULL", CompareOptions::caseInsensitive), "invalid UTF-8 is compared byte by byte instead of raising a TypeError on false");
        $this->assertSame(0, string_compare("MA\xF1ANA", "ma\xF1ana", CompareOptions::caseInsensitive), "the byte fallback keeps case insensitivity");
        $this->assertFalse(string_is_equal("ma\xF1ana", "NULL", CompareOptions::caseInsensitive));
    }

    public function testDiacriticInsensitiveOptionsLeaveInvalidUTF8Untouched(): void
    {
        $this->assertSame(0, string_compare("MA\xF1ANA", "ma\xF1ana", CompareOptions::caseInsensitive | CompareOptions::diacriticInsensitive), "invalid UTF-8 is not transliterated, so the comparison falls back to bytes instead of a fatal error");
        $this->assertSame(0, string_compare("ma\xF1ana", "ma\xF1ana", CompareOptions::normalized), "normalized comparison of invalid UTF-8");
        $this->assertTrue(string_contains("ma\xF1ana", "\xF1", CompareOptions::diacriticInsensitive), "string_contains goes through the same transformation");
        $this->assertSame(0, string_compare("árbol", "ARBOL", CompareOptions::caseInsensitive | CompareOptions::diacriticInsensitive), "valid UTF-8 is still transliterated");
    }

    public function testStringIsEqual(): void
    {
        $this->assertTrue(string_is_equal("HOLA", "hola", CompareOptions::caseInsensitive), "string_is_equal case-insensitive");
        $this->assertFalse(string_is_equal("hola", "adios"), "string_is_equal different strings");
    }

    public function testStringSearchNeedlesWithSlashes(): void
    {
        // Regression: needles containing "/" used to produce a broken pattern because
        // preg_quote() was called without the "/" delimiter.
        $this->assertTrue(string_contains("ruta a/b lista", "a/b"), "needle containing a slash");
        $this->assertTrue(string_begins_with("a/b/c", "a/b"), "prefix containing a slash");
        $this->assertTrue(string_ends_with("a/b/c", "b/c"), "suffix containing a slash");
        $this->assertTrue(string_matches("a/b", "a/b"), "exact match containing a slash");
        $this->assertFalse(string_contains("ruta axb lista", "a/b"), "slash needle does not act as a wildcard");
    }

    public function testStringSearchInvalidRawPattern(): void
    {
        // Regression: an invalid raw pattern (quoted option) must return 0 and reset
        // $matches instead of raising a TypeError on false.
        set_error_handler(fn(): bool => true);
        try {
            $matches = null;
            $count = string_search("abc", "/[invalid/", SearchMethod::matches, CompareOptions::quoted, $matches);
        } finally {
            restore_error_handler();
        }
        $this->assertSame(0, $count, "invalid raw pattern returns 0, got $count");
        $this->assertSame([], $matches, "invalid raw pattern resets matches to an empty array");
    }

    public function testStringSearchQuotesMetacharacters(): void
    {
        $this->assertNotSame(preg_quote(".+*?[^]$(){}=!<>|:-#/"), preg_quote(".+*?[^]$(){}=!<>|:-#/", "/"), "sanity: delimiter argument changes preg_quote output");
        $this->assertTrue(string_contains("precio (10.50) final", "(10.50)"), "needle with regex metacharacters is treated literally");
        $this->assertFalse(string_contains("precio 10X50 final", "(10.50)"), "dot in needle does not act as a wildcard");
    }

    public function testStringSearchCountsAndDerivatives(): void
    {
        $matches = null;
        $this->assertSame(3, string_search("uno dos uno tres uno", "uno", SearchMethod::contains, CompareOptions::none, $matches), "contains counts every occurrence");
        $this->assertSame(["uno", "uno", "uno"], $matches, "matches array holds the found substrings");

        $this->assertTrue(string_matches("hola", "hola"), "matches: identical strings");
        $this->assertFalse(string_matches("hola mundo", "hola"), "matches: partial string is not a full match");
        $this->assertTrue(string_begins_with("hola mundo", "hola"), "beginsWith: true case");
        $this->assertFalse(string_begins_with("hola mundo", "mundo"), "beginsWith: false case");
        $this->assertTrue(string_ends_with("hola mundo", "mundo"), "endsWith: true case");
        $this->assertFalse(string_ends_with("hola mundo", "hola"), "endsWith: false case");
        $this->assertTrue(string_contains("Hola Mundo", "mundo", CompareOptions::caseInsensitive), "contains: case-insensitive");
        $this->assertTrue(string_contains("el gato negro", "gato", CompareOptions::words), "words option matches a whole word");
        $this->assertFalse(string_contains("los gatos negros", "gato", CompareOptions::words), "words option rejects a partial word");
    }

    public function testStringHasPrefixAndSuffix(): void
    {
        $this->assertTrue(string_has_prefix("hola mundo", "hola"), "prefix present");
        $this->assertFalse(string_has_prefix("hola mundo", "mundo"), "prefix absent");
        $this->assertTrue(string_has_suffix("hola mundo", "mundo"), "suffix present");
        $this->assertFalse(string_has_suffix("hola mundo", "hola"), "suffix absent");
        $this->assertFalse(string_has_suffix("bc", "abc"), "suffix longer than the string");
    }

    public function testSubstringHelpers(): void
    {
        $this->assertSame("cdef", substring_from_index("abcdef", 2), "from positive index");
        $this->assertSame("ef", substring_from_index("abcdef", -2), "from negative index");
        $this->assertSame("abcdef", substring_from_index("abcdef", 0), "from zero index");
        $this->assertSame("abc", substring_to_index("abcdef", 3), "to positive index");
        $this->assertSame("", substring_to_index("abcdef", 0), "to zero index");
    }

    public function testIsSerialized(): void
    {
        // fdiv(0.0, 0.0) yields NAN at runtime; a literal NAN crashes Psalm 6.16.1
        foreach ([true, false, 42, -7, 0, 0.5, INF, -INF, fdiv(0.0, 0.0), "hola", "", "con \"comillas\"", [1, 2], ["k" => "v"], null] as $original) {
            $serialized = serialize($original);
            $this->assertTrue(is_serialized($serialized), "accepts real serialize() output: $serialized");
        }
        foreach (["b:2;", "b:10;", "i:1.5;", "i:E+3;", "d:INFX;", "s:99:\"hi\";", "s:2:\"hi\"", "hola", "N;N;"] as $invalid) {
            $this->assertFalse(is_serialized($invalid), "rejects invalid input: $invalid");
        }
        $this->assertFalse(is_serialized(42), "rejects non-string values");
    }

    public function testIsDirectoryJunction(): void
    {
        $directory = sys_get_temp_dir() . DIRECTORY_SEPARATOR . uniqid("standard-additions-junction-", true);
        $target = $directory . DIRECTORY_SEPARATOR . "target";
        $junction = $directory . DIRECTORY_SEPARATOR . "junction";
        mkdir($directory);
        mkdir($target);

        try {
            $this->assertFalse(is_directory_junction($directory), "a regular directory is not a directory junction");
            $this->assertFalse(is_directory_junction($directory . DIRECTORY_SEPARATOR . "missing"), "a missing path is not a directory junction");
            if (!TARGET_OS_WINDOWS) {
                return;
            }
            if (!function_exists("exec")) {
                $this->markTestSkipped("exec is required to create a Windows directory junction");
            }
            exec(sprintf("cmd /d /c mklink /J %s %s", escapeshellarg($junction), escapeshellarg($target)), $output, $status);
            if ($status !== 0) {
                $this->markTestSkipped("directory junctions are not supported in this environment");
            }
            $this->assertTrue(is_directory_junction($junction), "a Windows directory junction is identified as such");
        } finally {
            @rmdir($junction);
            @rmdir($target);
            @rmdir($directory);
        }
    }

    public function testArrayRemove(): void
    {
        $list = ["a", "b", "c"];
        array_remove($list, "b");
        $this->assertSame(["a", "c"], $list, "list is re-indexed after removal");

        // Regression: integer keys need not be consecutive for re-indexing to apply;
        // is_sequential() (first key is an int), not array_is_list(), is the contract.
        $gappy = [5 => "a", 9 => "b"];
        array_remove($gappy, "a");
        $this->assertSame([0 => "b"], $gappy, "gappy integer-keyed array is re-indexed");

        $mixed = [0 => "a", "x" => "b", 1 => "c"];
        array_remove($mixed, "a");
        $this->assertSame(["x" => "b", 1 => "c"], $mixed, "no re-indexing when the remaining first key is a string");

        $untouched = ["a", "b"];
        array_remove($untouched, "z");
        $this->assertSame(["a", "b"], $untouched, "missing element leaves the array untouched");
    }

    public function testCompareAndIsEqual(): void
    {
        $one = new ComparableInt(1);
        $two = new ComparableInt(2);
        $this->assertSame(-1, compare($one, $two), "both Comparable: ascending");
        $this->assertSame(1, compare($two, $one), "both Comparable: descending");
        $this->assertSame(0, compare($one, new ComparableInt(1)), "both Comparable: equal");
        // Regression: when only one side implements Comparable the result must be
        // consistent in both directions instead of falling back to <=>.
        $this->assertSame(1, compare($two, 1), "left-only Comparable");
        $this->assertSame(-1, compare(1, $two), "right-only Comparable is negated");
        $this->assertSame(-compare($two, 1), compare(1, $two), "asymmetric comparison is antisymmetric");
        $this->assertSame(-1, compare(3, 7), "neither Comparable falls back to spaceship");

        $this->assertTrue(is_equal($one, new ComparableInt(1)), "is_equal via Equatable");
        $this->assertFalse(is_equal($one, $two), "is_equal via Equatable: different");
        $this->assertTrue(is_equal(1, 1.0), "int and float with the same value");
        $this->assertFalse(is_equal(1, "1"), "int and numeric string are not equal");
        $this->assertTrue(is_equal("a", "a"), "identical strings");
    }

    public function testInRange(): void
    {
        $this->assertTrue(in_range(5, 1, 10), "value inside the range");
        $this->assertTrue(in_range(1, 1, 10), "minimum is inclusive");
        $this->assertFalse(in_range(10, 1, 10), "maximum is exclusive");
        $this->assertFalse(in_range(5, 10, 1), "inverted bounds are always false");
    }

    public function testCamelcase(): void
    {
        $this->assertSame("helloWorld", camelcase("hello world"), "camelcase with spaces");
        $this->assertSame("helloWorld", camelcase("hello_world"), "camelcase with underscores");
        $this->assertSame("helloWorldFoo", camelcase("hello-world-foo"), "camelcase with hyphens");
    }

    public function testBase64UrlEncode(): void
    {
        $this->assertSame("aGVsbG8_Pg", base64_url_encode("hello?>"), "base64 url-safe encoding without padding");
    }

    public function testRandomColor(): void
    {
        $color = random_color("hello");
        $this->assertMatchesRegularExpression("/^#[0-9A-F]{6}$/", $color, "random_color returns a hex color, got $color");
        $this->assertSame(random_color("hello"), $color, "random_color is deterministic");
    }

    public function testMiscHelpers(): void
    {
        $this->assertTrue(is_hidden(".hidden-nonexistent-file"), "dotfile fallback reports hidden (short shell output guard)");
        $this->assertFalse(is_hidden(__FILE__), "this test file is not hidden");

        $this->assertNotSame("", process_name(), "process_name returns a non-empty string");
        $this->assertIsString(document_root_directory(), "document_root_directory returns a string");
    }
}
