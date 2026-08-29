<?php

declare(strict_types=1);

namespace Sabatier\Foundation\Tests;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Sabatier\Foundation\ArrayClass;
use Sabatier\Foundation\Dictionary;
use Sabatier\Foundation\InternalInconsistencyException;
use Sabatier\Foundation\Predicates\Predicate;

/**
 * Tests for the expression half of src/Predicates/PredicateScanner.php — literals,
 * collection modifiers, arithmetic, function expressions and the index keywords.
 * PredicateScannerTest covers the predicate half (operator precedence and comparisons).
 *
 * Regression guards:
 *  - SOME was parsed as ALL plus a negation. It is a synonym for ANY, and "NOT ALL" is a
 *    different predicate: over {5,5,5}, "SOME nums == 5" answered false, and over {1,2,3}
 *    it answered true. The two disagreed wherever ANY and ALL disagree, which is most of
 *    the time;
 *  - the five bitwise functions and onesComplement declared int parameters, but the
 *    expression engine hands them whatever a key path or constant evaluated to, which is
 *    routinely a float — so every bitwise expression raised a TypeError before reaching
 *    its operator. They accept Number|int|float now and round to an integer, which is what
 *    MariaDB does (2.7 & 1 answers 1, not 0).
 */
final class PredicateScannerExpressionTest extends TestCase
{
    /**
     * @param array<string, mixed> $values
     */
    private function evaluate(string $format, array $values): bool
    {
        return Predicate::format($format)->evaluate(new Dictionary($values));
    }

    /**
     * @return iterable<string, array{string, list<int>, bool}>
     */
    public static function modifierProvider(): iterable
    {
        yield "ANY matches one member" => ["ANY nums == 5", [1, 5, 9], true];
        yield "ANY with no match" => ["ANY nums == 7", [1, 5, 9], false];
        yield "ALL holds for every member" => ["ALL nums > 0", [1, 5, 9], true];
        yield "ALL fails on one member" => ["ALL nums > 2", [1, 5, 9], false];
        yield "NONE holds when nothing matches" => ["NONE nums > 100", [1, 5, 9], true];
        yield "NONE fails when one matches" => ["NONE nums > 5", [1, 5, 9], false];
        // SOME is ANY: it must hold whenever at least one member matches, including when every member does.
        yield "SOME matches one member" => ["SOME nums == 5", [1, 5, 9], true];
        yield "SOME with every member matching" => ["SOME nums == 5", [5, 5, 5], true];
        yield "SOME with no match" => ["SOME nums == 5", [1, 2, 3], false];
    }

    /**
     * @param list<int> $numbers
     */
    #[DataProvider("modifierProvider")]
    public function testCollectionModifiers(string $format, array $numbers, bool $expected): void
    {
        $this->assertSame($expected, $this->evaluate($format, ["nums" => new ArrayClass($numbers)]));
    }

    /**
     * @return iterable<string, array{list<int>}>
     */
    public static function someAgreesWithAnyProvider(): iterable
    {
        yield "no member matches" => [[1, 2, 3]];
        yield "one member matches" => [[1, 5, 3]];
        yield "every member matches" => [[5, 5, 5]];
    }

    /**
     * @param list<int> $numbers
     */
    #[DataProvider("someAgreesWithAnyProvider")]
    public function testSomeIsASynonymForAny(array $numbers): void
    {
        $values = ["nums" => new ArrayClass($numbers)];

        $this->assertSame(
            $this->evaluate("ANY nums == 5", $values),
            $this->evaluate("SOME nums == 5", $values),
            "SOME and ANY must agree on " . json_encode($numbers)
        );
    }

    /**
     * @return iterable<string, array{string, bool}>
     */
    public static function literalProvider(): iterable
    {
        yield "TRUE" => ["flag == TRUE", true];
        yield "YES" => ["flag == YES", true];
        yield "FALSE" => ["flag == FALSE", false];
        yield "NO" => ["flag == NO", false];
    }

    #[DataProvider("literalProvider")]
    public function testBooleanLiterals(string $format, bool $expected): void
    {
        $this->assertSame($expected, $this->evaluate($format, ["flag" => true]));
    }

    public function testNullLiterals(): void
    {
        $this->assertSame("missing = null", Predicate::format("missing == NULL")->predicateFormat);
        $this->assertSame("missing = null", Predicate::format("missing == NIL")->predicateFormat);
        $this->assertTrue($this->evaluate("missing == NULL", ["other" => 1]));
    }

    public function testSelfComparesTheObjectItself(): void
    {
        $this->assertSame("SELF = 5", Predicate::format("SELF == 5")->predicateFormat);
    }

    /**
     * @return iterable<string, array{string, string, bool}>
     */
    public static function arithmeticProvider(): iterable
    {
        // n is 2 throughout. The format is the selector form the parser lowers to.
        yield "addition" => ["n + 1 == 3", "add:to:(n, 1) = 3", true];
        yield "subtraction" => ["n - 1 == 1", "from:subtract:(n, 1) = 1", true];
        yield "multiplication" => ["n * 2 == 4", "multiply:by:(n, 2) = 4", true];
        yield "division" => ["n / 2 == 1", "divide:by:(n, 2) = 1", true];
        yield "modulus" => ["n % 2 == 0", "modulus:by:(n, 2) = 0", true];
        yield "power" => ["n ** 2 == 4", "raise:toPower:(n, 2) = 4", true];
        // Multiplication binds tighter than addition, so this is n + (1 * 2).
        yield "multiplication before addition" => ["n + 1 * 2 == 4", "add:to:(n, multiply:by:(1, 2)) = 4", true];
        yield "parentheses inside an expression" => ["2 * (n + 1) == 6", "multiply:by:(2, add:to:(n, 1)) = 6", true];
    }

    #[DataProvider("arithmeticProvider")]
    public function testArithmeticExpressions(string $format, string $expectedFormat, bool $expected): void
    {
        $predicate = Predicate::format($format);

        $this->assertSame($expectedFormat, $predicate->predicateFormat);
        $this->assertSame($expected, $predicate->evaluate(new Dictionary(["n" => 2])));
    }

    public function testALeadingParenthesisIsReadAsAPredicateGroup(): void
    {
        // A "(" at the start of a comparison always opens a predicate group, never a parenthesised expression, so "(n) == 2" and "(n + 1) * 2 == 6" cannot parse while the same parentheses inside an expression can. Resolving it needs lookahead in the grammar without breaking "(a == 1 AND b == 2)", so it is recorded, not changed.
        $this->expectException(InternalInconsistencyException::class);

        Predicate::format("(n + 1) * 2 == 6")->predicateFormat;
    }

    /**
     * @return iterable<string, array{string, int, bool}>
     */
    public static function bitwiseProvider(): iterable
    {
        // n is 2. MariaDB: 2&1=0, 2|1=3, 2^3=1, 2<<1=4, 2>>1=1.
        yield "and" => ["bitwiseAnd:with:(n, 1) == 0", 2, true];
        yield "or" => ["bitwiseOr:with:(n, 1) == 3", 2, true];
        yield "xor" => ["bitwiseXor:with:(n, 3) == 1", 2, true];
        yield "left shift" => ["leftshift:by:(n, 1) == 4", 2, true];
        yield "right shift" => ["rightshift:by:(n, 1) == 1", 2, true];
    }

    #[DataProvider("bitwiseProvider")]
    public function testBitwiseFunctionsEvaluate(string $format, int $value, bool $expected): void
    {
        // These raised a TypeError before: the engine produced a float for the key path and the functions declared int.
        $this->assertSame($expected, $this->evaluate($format, ["n" => $value]));
    }

    public function testBitwiseOperandsRoundLikeMariaDB(): void
    {
        // SELECT 2.0 & 1, 2.7 & 1 -> 0, 1: the operand is rounded, not truncated.
        $this->assertTrue($this->evaluate("bitwiseAnd:with:(n, 1) == 0", ["n" => 2.0]));
        $this->assertTrue($this->evaluate("bitwiseAnd:with:(n, 1) == 1", ["n" => 2.7]));
    }

    /**
     * @return iterable<string, array{string, bool}>
     */
    public static function functionExpressionProvider(): iterable
    {
        yield "sum over an aggregate" => ["sum:({1,2,3}) == 6", true];
        yield "average over an aggregate" => ["average:({2,4}) == 3", true];
        yield "uppercase over a key path" => ["uppercase:(s) == \"ABC\"", true];
        yield "length over a key path" => ["length:(s) == 3", true];
        yield "abs over a negative constant" => ["abs:(-5) == 5", true];
    }

    #[DataProvider("functionExpressionProvider")]
    public function testFunctionExpressions(string $format, bool $expected): void
    {
        $this->assertSame($expected, $this->evaluate($format, ["s" => "abc"]));
    }

    /**
     * @return iterable<string, array{string, mixed}>
     */
    public static function indexKeywordProvider(): iterable
    {
        yield "FIRST" => ["arr[FIRST] == 1", true];
        yield "LAST" => ["arr[LAST] == 3", true];
        yield "SIZE" => ["arr[SIZE] == 3", true];
    }

    #[DataProvider("indexKeywordProvider")]
    public function testIndexKeywords(string $format, bool $expected): void
    {
        $this->assertSame($expected, $this->evaluate($format, ["arr" => new ArrayClass([1, 2, 3])]));
    }

    public function testAggregateLiteral(): void
    {
        $this->assertSame("n IN {1, 2, 3}", Predicate::format("n IN {1,2,3}")->predicateFormat);
        $this->assertTrue($this->evaluate("n IN {1,2,3}", ["n" => 2]));
    }

    public function testAVariableSurvivesIntoTheFormat(): void
    {
        $this->assertSame("x = \$valor", Predicate::format("x == \$valor")->predicateFormat);
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function malformedProvider(): iterable
    {
        yield "missing right operand" => ["x =="];
        yield "missing left operand" => ["== 5"];
        yield "split operator" => ["x = = 5"];
        yield "unclosed parenthesis" => ["(x == 1"];
        yield "IN without a list" => ["x IN"];
        yield "unclosed aggregate" => ["{1,2"];
    }

    #[DataProvider("malformedProvider")]
    public function testMalformedFormatsAreRejected(string $format): void
    {
        // The scanner reports a parse failure rather than returning a half-built predicate.
        $this->expectException(InternalInconsistencyException::class);

        Predicate::format($format)->predicateFormat;
    }

    public function testNegationOfAConstantLowersToChs(): void
    {
        $this->assertTrue($this->evaluate("n == -5", ["n" => -5]));
    }
}
