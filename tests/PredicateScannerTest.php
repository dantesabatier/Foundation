<?php

declare(strict_types=1);

namespace Sabatier\Foundation\Tests;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Sabatier\Foundation\ArrayClass;
use Sabatier\Foundation\Dictionary;
use Sabatier\Foundation\Predicates\CompoundPredicate;
use Sabatier\Foundation\Predicates\Predicate;

/**
 * Tests for src/Predicates/PredicateScanner.php.
 *
 * Regression guard:
 *  - the operator precedence was inverted. parsePredicate() entered the recursive
 *    descent at parseAnd(), whose operand parser was parseOr(), so OR bound tighter
 *    than AND: "a AND b OR c" parsed as "a AND (b OR c)" instead of "(a AND b) OR c",
 *    and "a AND b OR c AND d" produced an AND at the root where the standard grouping
 *    has an OR. This is not cosmetic — with a false, b false and c true the correct
 *    predicate is true and the parsed one is false. Every predicate language (SQL,
 *    NSPredicate, PHP, C) binds AND tighter than OR, and CoreData builds its WHERE
 *    clause from this tree, so an unparenthesised predicate also generated
 *    mis-grouped SQL and returned different rows. The chain is now
 *    parsePredicate -> parseOr -> parseAnd -> parseNot.
 */
final class PredicateScannerTest extends TestCase
{
    /**
     * @return iterable<string, array{string, string}>
     */
    public static function precedenceProvider(): iterable
    {
        yield "and before or" => ["a == 1 AND b == 2 OR c == 3", "(a = 1 AND b = 2) OR c = 3"];
        yield "or after and" => ["a == 1 OR b == 2 AND c == 3", "a = 1 OR (b = 2 AND c = 3)"];
        yield "two ands around an or" => ["a == 1 AND b == 2 OR c == 3 AND d == 4", "(a = 1 AND b = 2) OR (c = 3 AND d = 4)"];
        yield "and between two ors" => ["a == 1 OR b == 2 AND c == 3 OR d == 4", "a = 1 OR (b = 2 AND c = 3) OR d = 4"];
        yield "symbolic operators" => ["a == 1 && b == 2 || c == 3", "(a = 1 AND b = 2) OR c = 3"];
        // Same-type chains flatten, and explicit parentheses are honoured unchanged.
        yield "and chain" => ["a == 1 AND b == 2 AND c == 3", "a = 1 AND b = 2 AND c = 3"];
        yield "or chain" => ["a == 1 OR b == 2 OR c == 3", "a = 1 OR b = 2 OR c = 3"];
        yield "explicit and group" => ["(a == 1 AND b == 2) OR c == 3", "(a = 1 AND b = 2) OR c = 3"];
        yield "explicit or group" => ["a == 1 AND (b == 2 OR c == 3)", "a = 1 AND (b = 2 OR c = 3)"];
        yield "not binds to its comparison" => ["NOT a == 1 AND b == 2", "(NOT a = 1) AND b = 2"];
    }

    #[DataProvider("precedenceProvider")]
    public function testAndBindsTighterThanOr(string $format, string $expected): void
    {
        $this->assertSame($expected, Predicate::format($format)->predicateFormat);
    }

    /**
     * @return iterable<string, array{int, int, int}>
     */
    public static function truthTableProvider(): iterable
    {
        yield "all match" => [1, 2, 3];
        yield "only c" => [0, 0, 3];
        yield "only a" => [1, 0, 0];
        yield "only b" => [0, 2, 0];
        yield "a and b" => [1, 2, 0];
        yield "none" => [0, 0, 0];
        yield "a and c" => [1, 0, 3];
        yield "b and c" => [0, 2, 3];
    }

    /**
     * The parsed tree must agree with the language's own grouping, so compare against
     * PHP's operators rather than against a recorded expectation.
     */
    #[DataProvider("truthTableProvider")]
    public function testAndOrEvaluationMatchesStandardGrouping(int $a, int $b, int $c): void
    {
        $object = new Dictionary(["a" => $a, "b" => $b, "c" => $c]);

        $this->assertSame(
            ($a === 1 && $b === 2) || $c === 3,
            Predicate::format("a == 1 AND b == 2 OR c == 3")->evaluate($object),
            "a == 1 AND b == 2 OR c == 3"
        );
        $this->assertSame(
            $a === 1 || ($b === 2 && $c === 3),
            Predicate::format("a == 1 OR b == 2 AND c == 3")->evaluate($object),
            "a == 1 OR b == 2 AND c == 3"
        );
    }

    public function testParenthesesOverrideThePrecedence(): void
    {
        // a false and c true: "(a AND b) OR c" holds on c alone, while forcing the OR to the right of the AND makes the false a decide the whole predicate.
        $object = new Dictionary(["a" => 0, "b" => 0, "c" => 3]);

        $this->assertTrue(Predicate::format("a == 1 AND b == 2 OR c == 3")->evaluate($object));
        $this->assertFalse(Predicate::format("a == 1 AND (b == 2 OR c == 3)")->evaluate($object));
    }

    /**
     * @return iterable<string, array{string, bool}>
     */
    public static function comparisonOperatorProvider(): iterable
    {
        // n is 3. The two-character operators have to be matched before the one-character ones that open them: "<" used to consume the "<" of "<>" and leave "> 3" behind, so the documented "<>" spelling of != could not parse at all.
        yield "angle-bracket not equal, false" => ["n <> 3", false];
        yield "angle-bracket not equal, true" => ["n <> 5", true];
        yield "bang not equal, false" => ["n != 3", false];
        yield "bang not equal, true" => ["n != 5", true];
        yield "less than" => ["n < 5", true];
        yield "less than, false" => ["n < 2", false];
        yield "greater than" => ["n > 2", true];
        yield "greater than, false" => ["n > 5", false];
        yield "less than or equal" => ["n <= 3", true];
        yield "greater than or equal" => ["n >= 3", true];
        yield "reversed less than or equal" => ["n =< 3", true];
        yield "reversed greater than or equal" => ["n => 3", true];
    }

    #[DataProvider("comparisonOperatorProvider")]
    public function testEveryComparisonOperatorSpelling(string $format, bool $expected): void
    {
        $this->assertSame($expected, Predicate::format($format)->evaluate(new Dictionary(["n" => 3])));
    }

    /**
     * @return iterable<string, array{string, string}>
     */
    public static function formatProvider(): iterable
    {
        yield "beginswith" => ["name BEGINSWITH \"jo\"", "name BEGINSWITH 'jo'"];
        yield "contains with options" => ["name CONTAINS[c] \"AN\"", "name CONTAINS[c] 'AN'"];
        yield "between" => ["age BETWEEN {18,65}", "age BETWEEN {18, 65}"];
        yield "in" => ["name IN {\"a\",\"b\"}", "name IN {'a', 'b'}"];
        yield "greater than" => ["age > 18", "age > 18"];
        yield "like" => ["name LIKE \"j*\"", "name LIKE 'j*'"];
        yield "matches" => ["name MATCHES \".*x\"", "name MATCHES '.*x'"];
        yield "self" => ["SELF == 5", "SELF = 5"];
        yield "nil" => ["name == nil", "name = null"];
        yield "not null" => ["age != NULL", "age != null"];
        yield "truepredicate" => ["TRUEPREDICATE", "TRUEPREDICATE"];
        yield "falsepredicate" => ["FALSEPREDICATE", "FALSEPREDICATE"];
        yield "any" => ["ANY tags == \"x\"", "ANY tags = 'x'"];
        yield "all" => ["ALL scores > 5", "ALL scores > 5"];
        yield "key path" => ["a.b.c == 1", "a.b.c = 1"];
        yield "collection operator" => ["@count > 3", "@count > 3"];
    }

    #[DataProvider("formatProvider")]
    public function testComparisonFormatsRoundTrip(string $format, string $expected): void
    {
        $this->assertSame($expected, Predicate::format($format)->predicateFormat);
    }

    public function testCaseAndDiacriticInsensitiveComparison(): void
    {
        $predicate = Predicate::format("name ==[cd] \"JOSE\"");

        $this->assertTrue($predicate->evaluate(new Dictionary(["name" => "JOSE"])));
        $this->assertTrue($predicate->evaluate(new Dictionary(["name" => "jose"])));
        $this->assertTrue($predicate->evaluate(new Dictionary(["name" => "José"])));
        $this->assertFalse($predicate->evaluate(new Dictionary(["name" => "otro"])));
    }

    public function testConstantPredicatesEvaluateWithoutAnObject(): void
    {
        $this->assertTrue(Predicate::format("TRUEPREDICATE")->evaluate());
        $this->assertFalse(Predicate::format("FALSEPREDICATE")->evaluate());
    }

    public function testNotNegatesACompoundGroup(): void
    {
        $predicate = Predicate::format("NOT (a == 1 AND b == 2)");

        $this->assertSame("NOT (a = 1 AND b = 2)", $predicate->predicateFormat);
        $this->assertFalse($predicate->evaluate(new Dictionary(["a" => 1, "b" => 2])));
        $this->assertTrue($predicate->evaluate(new Dictionary(["a" => 1, "b" => 0])));
    }

    public function testUnbalancedParenthesisIsRejected(): void
    {
        $this->expectExceptionMessageMatches("/(closing|parse)/i");

        Predicate::format("(a == 1 AND b == 2")->predicateFormat;
    }

    public function testEmptyCompoundPredicatesUseTheirLogicalIdentities(): void
    {
        $this->assertTrue(CompoundPredicate::andPredicateWithSubpredicates(new ArrayClass())->evaluate());
        $this->assertFalse(CompoundPredicate::orPredicateWithSubpredicates(new ArrayClass())->evaluate());
    }

    public function testCompoundPredicatesShortCircuit(): void
    {
        $mustNotRun = Predicate::block(fn(): bool => throw new \LogicException("unreachable predicate evaluated"));

        $this->assertFalse(CompoundPredicate::andPredicateWithSubpredicates(new ArrayClass([Predicate::value(false), $mustNotRun]))->evaluate());
        $this->assertTrue(CompoundPredicate::orPredicateWithSubpredicates(new ArrayClass([Predicate::value(true), $mustNotRun]))->evaluate());
    }
}
