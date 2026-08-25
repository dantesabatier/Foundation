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
 * Regression guards:
 *  - LikePredicateOperator was an empty subclass of MatchingPredicateOperator, so LIKE
 *    evaluated its argument as a regular expression instead of a wildcard pattern. None
 *    of the wildcards worked ("hello" LIKE "h*" was false), some patterns additionally
 *    emitted a preg_match_all() compilation warning because the bare "*" reached the
 *    regex engine as a quantifier with nothing to repeat, and the operator only ever
 *    matched on literal equality. The pattern is now quoted, "*" and "?" are reinstated
 *    as ".*" and ".", and it is passed as already-quoted so string_search() does not
 *    escape them straight back;
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
        // Expectations are MariaDB LIKE results with "%" written as "*" and "_" as "?".
        yield "trailing wildcard" => ["hello", "h*", true];
        yield "leading wildcard" => ["hello", "*o", true];
        yield "surrounding wildcards" => ["hello", "*ell*", true];
        yield "single character wildcard" => ["hello", "h?llo", true];
        yield "no wildcard, equal" => ["hello", "hello", true];
        yield "wildcard in the middle" => ["hello", "h*o", true];
        yield "bare wildcard" => ["hello", "*", true];
        yield "wildcard between literals" => ["hello", "he*lo", true];
        yield "no match" => ["hello", "x*", false];
        // A dot is a literal in a wildcard pattern, not the regex metacharacter:
        // SELECT 'a.c' LIKE 'a.c' -> 1 while SELECT 'abc' LIKE 'a.c' -> 0.
        yield "literal dot matches" => ["a.c", "a.c", true];
        yield "literal dot does not match any character" => ["abc", "a.c", false];
        // LIKE is case sensitive without an explicit option.
        yield "case sensitive" => ["hello", "HELLO", false];
        yield "case sensitive with wildcard" => ["hello", "h*LO", false];
        // Anchored at both ends: a prefix alone is not a match.
        yield "partial pattern is not a match" => ["hello", "hell", false];
    }

    #[DataProvider("likeProvider")]
    public function testLikeTreatsItsArgumentAsAWildcardPattern(string $value, string $pattern, bool $expected): void
    {
        $this->assertSame($expected, $this->evaluate("s LIKE \"$pattern\"", ["s" => $value]));
    }

    public function testLikeHonoursTheCaseInsensitiveOption(): void
    {
        $this->assertTrue($this->evaluate("s LIKE[c] \"HELLO\"", ["s" => "hello"]));
        $this->assertTrue($this->evaluate("s LIKE[c] \"H*O\"", ["s" => "hello"]));
    }

    public function testLikeDoesNotWarnOnABareWildcard(): void
    {
        // The bare "*" used to reach the regex engine as a quantifier with nothing to
        // repeat, so this raised a preg_match_all() compilation warning. failOnWarning
        // is enabled, which makes the warning itself a failure.
        $this->assertTrue($this->evaluate("s LIKE \"*\"", ["s" => "anything"]));
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
        // MATCHES is the operator that really does take a regex, which is what LIKE was wrongly sharing an implementation with. It matches against the whole string, so a pattern that only covers part of it does not match on its own.
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
