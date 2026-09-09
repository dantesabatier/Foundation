<?php

declare(strict_types=1);

namespace Sabatier\Foundation\Tests;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Sabatier\Foundation\ArrayClass;
use Sabatier\Foundation\ComparisonResult;
use Sabatier\Foundation\Number;
use Sabatier\Foundation\ObjectClass;
use Sabatier\Foundation\Set;

/**
 * Tests src/Number.php, which had no suite of its own even though its compare() and
 * isEqual() are the framework's reference implementation of the identity-versus-equality
 * convention CLAUDE.md records as settled.
 *
 * Regression guards:
 *  - two Numbers holding the same value are equal while keeping distinct identity
 *    hashes. $hash is spl_object_id() and no subclass overrides it, so conceptual
 *    equality lives entirely in isEqual()/compare(). The NSObject rule that equal
 *    objects must hash equally deliberately does not apply here, and a reader who knows
 *    that rule is the one most likely to "fix" it;
 *  - the collections rely on that: Set deduplicates, ArrayClass searches and sort()
 *    orders through isEqual()/compare() rather than through hashes, so breaking Number's
 *    equality changes how every collection holding one behaves;
 *  - compare() converts the receiver to the type of the operand rather than the other
 *    way round, which is why comparing against a string is a string comparison — "42"
 *    sorts before "9" — and comparing against a bool asks only whether the number is
 *    non-zero;
 *  - an operand it cannot compare falls through to the parent, which answers a stable
 *    order rather than raising.
 */
final class NumberTest extends TestCase
{
    public function testTheSameValueIsEqualWithoutSharingAnIdentity(): void
    {
        $number = new Number(42);
        $other = new Number(42);

        $this->assertTrue($number->isEqual($other), "equal values are equal");
        $this->assertNotSame($number->hash, $other->hash, "and keep distinct identity hashes");
        $this->assertSame($number->hash, $number->hash, "an instance is its own identity");
    }

    public function testCollectionsDeduplicateAndSearchThroughEquality(): void
    {
        $set = new Set([new Number(1), new Number(2), new Number(1)]);

        $this->assertSame(2, $set->count, "the repeated value is dropped even though the two instances differ");

        $array = new ArrayClass([new Number(5), new Number(9)]);

        $this->assertTrue($array->containsElement(new Number(9)), "membership is by value, not by instance");
        $this->assertSame(1, $array->indexOf(new Number(9)));
    }

    public function testSortingOrdersThroughCompare(): void
    {
        $numbers = new ArrayClass([new Number(3), new Number(1), new Number(2)]);

        $numbers->sort();

        $this->assertSame([1, 2, 3], $numbers->map(fn(Number $number): int => $number->intValue)->array);
    }

    /** @return iterable<string, array{mixed, ComparisonResult}> */
    public static function comparisonProvider(): iterable
    {
        yield "smaller integer" => [41, ComparisonResult::orderedDescending];
        yield "equal integer" => [42, ComparisonResult::orderedSame];
        yield "larger integer" => [43, ComparisonResult::orderedAscending];
        yield "equal float" => [42.0, ComparisonResult::orderedSame];
        yield "smaller float" => [41.9, ComparisonResult::orderedDescending];
        yield "equal string" => ["42", ComparisonResult::orderedSame];
        yield "equal Number" => [new Number(42), ComparisonResult::orderedSame];
        yield "smaller Number" => [new Number(41), ComparisonResult::orderedDescending];
    }

    #[DataProvider("comparisonProvider")]
    public function testComparisonAnswersTheOrderingOfTheOperand(mixed $other, ComparisonResult $expected): void
    {
        $this->assertSame($expected, new Number(42)->compare($other));
    }

    public function testComparingAgainstAStringIsAStringComparison(): void
    {
        // The receiver is rendered as a string to meet the operand, so the ordering is lexicographic: "42" sorts before "9" even though 42 is the larger number.
        $this->assertSame(ComparisonResult::orderedAscending, new Number(42)->compare("9"));
        $this->assertSame(ComparisonResult::orderedSame, new Number(42)->compare("42"));
    }

    public function testComparingAgainstABooleanAsksOnlyWhetherTheNumberIsSet(): void
    {
        $this->assertSame(ComparisonResult::orderedSame, new Number(42)->compare(true), "any non-zero number is true");
        $this->assertSame(ComparisonResult::orderedSame, new Number(1)->compare(true));
        $this->assertSame(ComparisonResult::orderedSame, new Number(0)->compare(false));
        $this->assertSame(ComparisonResult::orderedDescending, new Number(42)->compare(false));
    }

    /** @return iterable<string, array{mixed}> */
    public static function incomparableProvider(): iterable
    {
        yield "null" => [null];
        yield "object" => [new ObjectClass()];
        yield "array" => [[1, 2]];
    }

    #[DataProvider("incomparableProvider")]
    public function testAnIncomparableOperandStillAnswersAnOrdering(mixed $other): void
    {
        $result = new Number(42)->compare($other);

        $this->assertContains($result, [ComparisonResult::orderedAscending, ComparisonResult::orderedDescending], "the parent answers a stable order rather than raising");
        $this->assertFalse(new Number(42)->isEqual($other), "and never reports equality");
    }

    /** @return iterable<string, array{Number|bool|float|int|string, int, float, bool, string}> */
    public static function constructionProvider(): iterable
    {
        yield "integer" => [42, 42, 42.0, true, "42"];
        yield "zero" => [0, 0, 0.0, false, "0"];
        yield "float" => [3.5, 3, 3.5, true, "3.5"];
        yield "numeric string" => ["7", 7, 7.0, true, "7"];
        yield "boolean" => [true, 1, 1.0, true, "true"];
    }

    #[DataProvider("constructionProvider")]
    public function testEveryScalarIsReadableAsEveryType(Number|bool|float|int|string $value, int $asInt, float $asFloat, bool $asBool, string $asString): void
    {
        $number = new Number($value);

        $this->assertSame($asInt, $number->intValue);
        $this->assertSame($asFloat, $number->floatValue);
        $this->assertSame($asFloat, $number->doubleValue, "double is a synonym for float");
        $this->assertSame($asBool, $number->boolValue);
        $this->assertSame($asString, $number->stringValue);
    }

    public function testAThousandsSeparatorIsStrippedOnConstruction(): void
    {
        $this->assertSame(1234, new Number("1,234")->intValue, "a formatted numeric string is accepted");
        $this->assertSame(1234567, new Number("1,234,567")->intValue);
    }

    public function testANumberCanBeBuiltFromAnotherNumber(): void
    {
        $this->assertSame(7, new Number(new Number(7))->intValue, "the value is unwrapped rather than nested");
    }
}
