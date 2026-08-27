<?php

declare(strict_types=1);

namespace Sabatier\Foundation\Tests;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Sabatier\Foundation\Dictionary;
use Sabatier\Foundation\Predicates\Predicate;

/**
 * Tests for src/Predicates/ComparisonPredicate.php and the PredicateOperator subclasses
 * its evaluation dispatches to.
 *
 * LIKE deliberately expands no wildcard, in either syntax: "*" and "?" are
 * NSPredicate's, "%" and "_" are SQL's, and none of them stands for anything. That is
 * what makes an exact-looking LIKE on a value holding a literal "%" or "_" work, which
 * is common in SKUs, folios and part numbers, and CoreData lowers it to SQL
 * `LIKE BINARY` with those two characters escaped for exactly that reason. Teaching
 * either layer to expand "*" or "?" breaks the parity and makes one predicate answer
 * differently depending on whether it reached the database — which is why the wildcard
 * support attempted here was reverted. A caller who wants a prefix or substring search
 * uses BEGINSWITH / CONTAINS / ENDSWITH, and one who wants a regex uses MATCHES. See
 * CoreData's SQLCaseSensitivityTest and SQLWildcardEscapingTest, which pin the contract
 * from the SQL side.
 *
 * The parity is not total, and testLikeLeaksRegexMetacharactersInMemory records where it
 * ends: in memory the pattern reaches string_matches() already marked as quoted, so a
 * regex metacharacter inside it survives ("abc" LIKE "a.c" holds), while the SQL side
 * escapes only "%" and "_" and compares the dot literally. Documented rather than
 * changed, because closing it means picking which layer moves.
 *
 * Regression guards:
 *  - InPredicateOperator typed its comparison closure as string, so "n IN {1,5,9}"
 *    raised a TypeError for a numeric collection. Only collections of strings worked.
 *    It compares through is_equal() now, keeping the options-aware string comparison for
 *    the case where both sides really are strings;
 *  - BetweenPredicateOperator evaluated through in_range(), which is half-open by
 *    design (min <= value < max) and also demands max > min. BETWEEN is inclusive at
 *    both ends, so it rejected its own upper bound ("5 BETWEEN {1,5}" was false where
 *    MariaDB answers 1) and rejected every single-point range outright. It compares
 *    through compare() now; in_range() itself is correct and untouched.
 */
final class ComparisonPredicateTest extends TestCase
{
    /**
     * @param array<string, mixed> $values
     */
    private function evaluate(string $format, array $values): bool
    {
        return Predicate::format($format)->evaluate(new Dictionary($values));
    }

    /**
     * @return iterable<string, array{string, bool}>
     */
    public static function relationalProvider(): iterable
    {
        yield "less than" => ["n < 10", true];
        yield "less than, equal" => ["n < 5", false];
        yield "less than or equal" => ["n <= 5", true];
        yield "greater than" => ["n > 1", true];
        yield "greater than, equal" => ["n > 5", false];
        yield "greater than or equal" => ["n >= 5", true];
        yield "equal" => ["n == 5", true];
        yield "equal, false" => ["n == 4", false];
        yield "not equal" => ["n != 3", true];
        yield "not equal, false" => ["n != 5", false];
    }

    #[DataProvider("relationalProvider")]
    public function testRelationalOperators(string $format, bool $expected): void
    {
        $this->assertSame($expected, $this->evaluate($format, ["n" => 5]));
    }

    /**
     * @return iterable<string, array{string, string, bool}>
     */
    public static function likeProvider(): iterable
    {
        // Neither of the two wildcard syntaxes a caller might reach for works: "*" and
        // "?" are NSPredicate's, and "%" and "_" are SQL's, and none of them expands.
        yield "exact match" => ["hello", "hello", true];
        yield "different value" => ["hello", "world", false];
        yield "nspredicate asterisk does not expand" => ["hello", "h*", false];
        yield "nspredicate question mark does not expand" => ["hello", "h?llo", false];
        yield "sql percent does not expand" => ["hello", "h%", false];
        yield "sql underscore does not expand" => ["hello", "h_llo", false];
        // The values SQLWildcardEscapingTest protects: an exact-looking LIKE on a value
        // holding a literal "%" or "_" has to find it, which is why the SQL side escapes
        // them rather than expanding them.
        yield "a literal percent matches itself" => ["50%OFF", "50%OFF", true];
        yield "a literal underscore matches itself" => ["AUDIT_TEST", "AUDIT_TEST", true];
        yield "underscore does not stand in for a character" => ["AUDITxTEST", "AUDIT_TEST", false];
        yield "case sensitive" => ["hello", "HELLO", false];
        yield "a prefix alone is not a match" => ["hello", "hell", false];
    }

    #[DataProvider("likeProvider")]
    public function testLikeExpandsNoWildcard(string $value, string $pattern, bool $expected): void
    {
        $this->assertSame($expected, $this->evaluate("s LIKE \"$pattern\"", ["s" => $value]));
    }

    /**
     * @return iterable<string, array{string, string, bool}>
     */
    public static function likeRegexLeakProvider(): iterable
    {
        // In memory LIKE reaches string_matches() through MatchingPredicateOperator,
        // which forces CompareOptions::quoted — so the pattern is NOT preg_quote'd and
        // regex metacharacters survive into the compiled pattern. The SQL side escapes
        // only "%" and "_", so these patterns are the ones where the two layers part
        // company; they are pinned as they behave, not as they ought to.
        yield "a dot matches any character" => ["abc", "a.c", true];
        yield "a regex quantifier expands" => ["hello", "h.*o", true];
        yield "a character class expands" => ["hello", "h[ae]llo", true];
    }

    #[DataProvider("likeRegexLeakProvider")]
    public function testLikeLeaksRegexMetacharactersInMemory(string $value, string $pattern, bool $expected): void
    {
        $this->assertSame($expected, $this->evaluate("s LIKE \"$pattern\"", ["s" => $value]));
    }

    public function testLikeHonoursTheCaseInsensitiveOption(): void
    {
        $this->assertTrue($this->evaluate("s LIKE[c] \"HELLO\"", ["s" => "hello"]));
        $this->assertFalse($this->evaluate("s LIKE[c] \"WORLD\"", ["s" => "hello"]));
    }

    /**
     * @return iterable<string, array{string, mixed, bool}>
     */
    public static function inProvider(): iterable
    {
        yield "integer present" => ["n IN {1,5,9}", 5, true];
        yield "integer absent" => ["n IN {2,3}", 5, false];
        yield "integer first" => ["n IN {1,5,9}", 1, true];
        yield "integer last" => ["n IN {1,5,9}", 9, true];
        yield "float present" => ["n IN {1.5,2.5}", 2.5, true];
        yield "float absent" => ["n IN {1.5,3.5}", 2.5, false];
    }

    #[DataProvider("inProvider")]
    public function testInAcceptsNumericCollections(string $format, mixed $value, bool $expected): void
    {
        $this->assertSame($expected, $this->evaluate($format, ["n" => $value]));
    }

    public function testInOverStrings(): void
    {
        $this->assertTrue($this->evaluate("s IN {\"a\",\"b\"}", ["s" => "a"]));
        $this->assertFalse($this->evaluate("s IN {\"a\",\"b\"}", ["s" => "z"]));
    }

    public function testInAgainstAStringIsASubstringCheck(): void
    {
        // "IN" with a string on the right asks whether the left is contained in it.
        $this->assertTrue($this->evaluate("s IN \"hello\"", ["s" => "ell"]));
        $this->assertFalse($this->evaluate("s IN \"hello\"", ["s" => "xyz"]));
    }

    /**
     * @return iterable<string, array{string, bool}>
     */
    public static function substringProvider(): iterable
    {
        yield "beginswith" => ["s BEGINSWITH \"he\"", true];
        yield "beginswith, false" => ["s BEGINSWITH \"lo\"", false];
        yield "endswith" => ["s ENDSWITH \"lo\"", true];
        yield "endswith, false" => ["s ENDSWITH \"he\"", false];
        yield "contains" => ["s CONTAINS \"ell\"", true];
        yield "contains, false" => ["s CONTAINS \"xyz\"", false];
    }

    #[DataProvider("substringProvider")]
    public function testSubstringOperators(string $format, bool $expected): void
    {
        $this->assertSame($expected, $this->evaluate($format, ["s" => "hello"]));
    }

    public function testMatchesEvaluatesARegularExpression(): void
    {
        // MATCHES is the operator that takes a regex — the one to reach for when LIKE's literal comparison is not what is wanted. It matches against the whole string, so a pattern that only covers part of it does not match on its own.
        $this->assertTrue($this->evaluate("s MATCHES \"^h.*o$\"", ["s" => "hello"]));
        $this->assertTrue($this->evaluate("s MATCHES \".*ll.*\"", ["s" => "hello"]));
        $this->assertFalse($this->evaluate("s MATCHES \"^x\"", ["s" => "hello"]));
        $this->assertFalse($this->evaluate("s MATCHES \"l+\"", ["s" => "hello"]));
    }

    public function testBetweenIsInclusiveAtBothEnds(): void
    {
        // SELECT 5 BETWEEN 1 AND 10, 5 BETWEEN 5 AND 10, 5 BETWEEN 1 AND 5,
        //        5 BETWEEN 5 AND 5, 5 BETWEEN 6 AND 10 -> 1, 1, 1, 1, 0
        $this->assertTrue($this->evaluate("n BETWEEN {1,10}", ["n" => 5]));
        $this->assertTrue($this->evaluate("n BETWEEN {5,10}", ["n" => 5]), "lower bound");
        $this->assertTrue($this->evaluate("n BETWEEN {1,5}", ["n" => 5]), "upper bound");
        $this->assertTrue($this->evaluate("n BETWEEN {5,5}", ["n" => 5]), "single-point range");
        $this->assertFalse($this->evaluate("n BETWEEN {6,10}", ["n" => 5]));
        $this->assertFalse($this->evaluate("n BETWEEN {0,4}", ["n" => 5]));
    }

    public function testCaseAndDiacriticInsensitiveEquality(): void
    {
        $this->assertTrue($this->evaluate("s ==[cd] \"JOSE\"", ["s" => "josé"]));
        $this->assertTrue($this->evaluate("s ==[c] \"HELLO\"", ["s" => "hello"]));
        $this->assertFalse($this->evaluate("s ==[c] \"WORLD\"", ["s" => "hello"]));
    }

    public function testSubstringOperatorsHonourTheCaseInsensitiveOption(): void
    {
        $this->assertTrue($this->evaluate("s BEGINSWITH[c] \"HE\"", ["s" => "hello"]));
        $this->assertTrue($this->evaluate("s CONTAINS[c] \"ELL\"", ["s" => "hello"]));
        $this->assertTrue($this->evaluate("s ENDSWITH[c] \"LO\"", ["s" => "hello"]));
    }

    public function testComparisonAgainstAMissingKeyIsFalse(): void
    {
        // A Dictionary answers null for a missing key, and the operators must treat that
        // as "no match" rather than raising.
        $this->assertFalse($this->evaluate("missing == 5", ["n" => 5]));
        $this->assertFalse($this->evaluate("missing BEGINSWITH \"a\"", ["n" => 5]));
        $this->assertFalse($this->evaluate("missing LIKE \"a*\"", ["n" => 5]));
    }
}
