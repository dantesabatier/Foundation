<?php

declare(strict_types=1);

namespace Sabatier\Foundation\Tests;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Sabatier\Foundation\ArrayClass;
use Sabatier\Foundation\Dictionary;
use Sabatier\Foundation\InternalInconsistencyException;
use Sabatier\Foundation\Predicates\CompoundPredicate;
use Sabatier\Foundation\Predicates\Predicate;

/**
 * Tests for the corners of src/Predicates/PredicateScanner.php the other predicate
 * suites do not reach: printf-style format specifiers, collection indexing, set
 * operations, and the flattening of nested AND/OR chains.
 *
 * Regression guards:
 *  - every printf-style specifier consumes exactly one argument: %K takes a key path,
 *    the value specifiers (%@, %d, %s, %f, the integer and float families) take a
 *    constant, and the width-prefixed forms (%hi, %hu, %qi, %qx) behave as their base;
 *  - the indexing suffixes parse to the function expressions they stand for —
 *    [FIRST] to first:, [LAST] to last:, [SIZE] to size:, and [n] to index: — and
 *    evaluate against a real collection;
 *  - UNION, INTERSECT and MINUS parse as expressions and are only reachable inside a
 *    comparison, never as a predicate on their own;
 *  - a chain of AND (or OR) flattens into one compound predicate rather than nesting,
 *    including when both sides are already compounds of the same type;
 *  - a malformed format reports where it gave up instead of returning a broken tree.
 */
final class PredicateScannerFormatTest extends TestCase
{
    /** @return iterable<string, array{string, list<mixed>, string}> */
    public static function formatSpecifierProvider(): iterable
    {
        yield "object" => ["%K == %@", ["name", "Ada"], "name = 'Ada'"];
        yield "string" => ["%K == %s", ["city", "Paris"], "city = 'Paris'"];
        yield "decimal" => ["%K == %d", ["age", 42], "age = 42"];
        yield "integer" => ["%K == %i", ["n", 7], "n = 7"];
        yield "unsigned" => ["%K == %u", ["n", 7], "n = 7"];
        yield "octal" => ["%K == %o", ["oct", 8], "oct = 8"];
        yield "hexadecimal" => ["%K == %x", ["hex", 255], "hex = 255"];
        yield "float" => ["%K == %f", ["ratio", 1.5], "ratio = 1.5"];
        yield "short integer" => ["%K == %hi", ["n", 7], "n = 7"];
        yield "short unsigned" => ["%K == %hu", ["n", 7], "n = 7"];
        yield "long long integer" => ["%K == %qi", ["big", 99], "big = 99"];
        yield "long long hexadecimal" => ["%K == %qx", ["big", 99], "big = 99"];
    }

    /** @param list<mixed> $arguments */
    #[DataProvider("formatSpecifierProvider")]
    public function testEachFormatSpecifierConsumesOneArgument(string $format, array $arguments, string $expected): void
    {
        $predicate = Predicate::format($format, new ArrayClass($arguments));

        $this->assertNotNull($predicate);
        $this->assertSame($expected, $predicate->predicateFormat);
    }

    public function testAKeyPathSpecifierIsAKeyPathAndNotAConstant(): void
    {
        $predicate = Predicate::format("%K == %@", new ArrayClass(["name", "name"]));

        // Both arguments are the same string; only the %K one becomes a key path, so the rendered format quotes the constant and leaves the key path bare.
        $this->assertNotNull($predicate);
        $this->assertSame("name = 'name'", $predicate->predicateFormat);
    }

    /** @return iterable<string, array{string, string}> */
    public static function indexingProvider(): iterable
    {
        yield "first" => ["items[FIRST] == 1", "first:(items) = 1"];
        yield "last" => ["items[LAST] == 9", "last:(items) = 9"];
        yield "size" => ["items[SIZE] == 3", "size:(items) = 3"];
        yield "positional" => ["items[1] == 5", "index:(items, 1) = 5"];
    }

    #[DataProvider("indexingProvider")]
    public function testIndexingSuffixesParseToTheirFunctionExpression(string $format, string $expected): void
    {
        $predicate = Predicate::format($format);

        $this->assertNotNull($predicate);
        $this->assertSame($expected, $predicate->predicateFormat);
    }

    /** @return iterable<string, array{string}> */
    public static function indexingEvaluationProvider(): iterable
    {
        yield "first" => ["items[FIRST] == 1"];
        yield "last" => ["items[LAST] == 9"];
        yield "size" => ["items[SIZE] == 3"];
        yield "positional" => ["items[1] == 5"];
    }

    #[DataProvider("indexingEvaluationProvider")]
    public function testIndexingEvaluatesAgainstACollection(string $format): void
    {
        $object = new Dictionary(["items" => new ArrayClass([1, 5, 9])]);

        $predicate = Predicate::format($format);

        $this->assertNotNull($predicate);
        $this->assertTrue($predicate->evaluate($object));
    }

    public function testAMissingClosingBracketIsReported(): void
    {
        $this->expectException(InternalInconsistencyException::class);
        $this->expectExceptionMessage("missing closing \"]\"");

        Predicate::format("items[1 == 2");
    }

    /** @return iterable<string, array{string, list<int>, string}> */
    public static function setOperationProvider(): iterable
    {
        yield "union" => ["a UNION b == %@", [1, 2, 3], "a UNION b = [1, 2, 3]"];
        yield "intersect" => ["a INTERSECT b == %@", [2], "a INTERSECT b = [2]"];
        yield "minus" => ["a MINUS b == %@", [1], "a MINUS b = [1]"];
    }

    /** @param list<int> $expectedMembers */
    #[DataProvider("setOperationProvider")]
    public function testSetOperationsParseAndEvaluateInsideAComparison(string $format, array $expectedMembers, string $expectedFormat): void
    {
        $object = new Dictionary(["a" => new ArrayClass([1, 2]), "b" => new ArrayClass([2, 3])]);

        $predicate = Predicate::format($format, new ArrayClass([new ArrayClass($expectedMembers)]));

        $this->assertNotNull($predicate);
        $this->assertSame($expectedFormat, $predicate->predicateFormat);
        $this->assertTrue($predicate->evaluate($object));
    }

    /** @return iterable<string, array{string}> */
    public static function bareSetOperationProvider(): iterable
    {
        yield "union" => ["a UNION b"];
        yield "intersect" => ["a INTERSECT b"];
        yield "minus" => ["a MINUS b"];
    }

    #[DataProvider("bareSetOperationProvider")]
    public function testASetOperationIsAnExpressionAndNotAPredicate(string $format): void
    {
        $this->expectException(InternalInconsistencyException::class);

        Predicate::format($format);
    }

    /** @return iterable<string, array{string, int, string}> */
    public static function flatteningProvider(): iterable
    {
        yield "and chain" => ["a == 1 AND b == 2 AND c == 3", 3, "a = 1 AND b = 2 AND c = 3"];
        yield "or chain" => ["a == 1 OR b == 2 OR c == 3", 3, "a = 1 OR b = 2 OR c = 3"];
        yield "nested and groups" => ["(a == 1 AND b == 2) AND (c == 3 AND d == 4)", 4, "a = 1 AND b = 2 AND c = 3 AND d = 4"];
        yield "nested or groups" => ["(a == 1 OR b == 2) OR (c == 3 OR d == 4)", 4, "a = 1 OR b = 2 OR c = 3 OR d = 4"];
    }

    #[DataProvider("flatteningProvider")]
    public function testChainsOfTheSameOperatorFlattenIntoOneCompound(string $format, int $expectedCount, string $expectedFormat): void
    {
        $predicate = Predicate::format($format);

        $this->assertInstanceOf(CompoundPredicate::class, $predicate);
        $this->assertSame($expectedCount, $predicate->subpredicates->count, "the chain collapses instead of nesting pairwise");
        $this->assertSame($expectedFormat, $predicate->predicateFormat);
    }

    /** @return iterable<string, array{string, string}> */
    public static function symbolicOperatorProvider(): iterable
    {
        yield "double ampersand" => ["a == 1 && b == 2", "a = 1 AND b = 2"];
        yield "double pipe" => ["a == 1 || b == 2", "a = 1 OR b = 2"];
    }

    #[DataProvider("symbolicOperatorProvider")]
    public function testSymbolicLogicalOperatorsAreTheirKeywordEquivalent(string $format, string $expected): void
    {
        $predicate = Predicate::format($format);

        $this->assertNotNull($predicate);
        $this->assertSame($expected, $predicate->predicateFormat);
    }

    /** @return iterable<string, array{string, string}> */
    public static function malformedFormatProvider(): iterable
    {
        yield "dangling operator" => ["a ==", "parsing error"];
        yield "unclosed group" => ["((a == 1)", "missing closing \")\""];
        yield "nonsense" => ["@@@", "parsing error"];
    }

    #[DataProvider("malformedFormatProvider")]
    public function testAMalformedFormatReportsWhereItGaveUp(string $format, string $expected): void
    {
        $this->expectException(InternalInconsistencyException::class);
        $this->expectExceptionMessage($expected);

        Predicate::format($format);
    }
}
