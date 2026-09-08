<?php

declare(strict_types=1);

namespace Sabatier\Foundation\Tests;

use AssertionError;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Sabatier\Foundation\CompareOptions;
use Sabatier\Foundation\ComparisonResult;
use Sabatier\Foundation\Nil;
use Sabatier\Foundation\Number;
use Sabatier\Foundation\Value;
use stdClass;

use function Sabatier\Foundation\pn;
use function Sabatier\Foundation\pv;
use function Sabatier\Foundation\string_with_options;

final class ValueTest extends TestCase
{
    /** @return iterable<string, array{string, mixed}> */
    public static function stringValueProvider(): iterable
    {
        yield "null" => ["NULL", null];
        yield "nil" => ["nil", null];
        yield "true" => ["TRUE", true];
        yield "yes" => ["yes", true];
        yield "false" => ["FALSE", false];
        yield "no" => ["no", false];
        yield "integer" => ["42", 42];
        yield "leading zero" => ["0123", 123];
        yield "decimal" => ["1.5", 1.5];
        yield "scientific" => ["1e3", 1000.0];
        yield "text" => ["foundation", "foundation"];
    }

    #[DataProvider("stringValueProvider")]
    public function testValueConvertsRecognizedStringLiterals(string $input, mixed $expected): void
    {
        $this->assertSame($expected, new Value($input)->value);
    }

    public function testValueUnwrapsAnotherValue(): void
    {
        $this->assertSame(42, new Value(new Value(42))->value);
    }

    public function testNilIsTheSharedNullValue(): void
    {
        $nil = Nil::nil();

        $this->assertSame($nil, Nil::nil());
        $this->assertNull($nil->value);
        $this->assertSame("null", $nil->type);
        $this->assertSame("null", $nil->description);
    }

    public function testValueDescribesComparesAndSerializesItsContents(): void
    {
        $value = new Value(42);

        $this->assertSame("42", $value->description);
        $this->assertStringContainsString("(int)42", $value->debugDescription);
        $this->assertSame(42, $value->jsonSerialize());
        $this->assertSame(ComparisonResult::orderedSame, $value->compare(42));
        $this->assertSame(ComparisonResult::orderedAscending, $value->compare(42.0));
        $this->assertSame(ComparisonResult::orderedDescending, $value->compare(new stdClass()));
        $this->assertTrue($value->isEqual(new Value(42)));

        $restored = unserialize(serialize($value));
        $this->assertInstanceOf(Value::class, $restored);
        $this->assertSame(42, $restored->value);
        $this->assertSame("int", $restored->type);
    }

    public function testNumberAcceptsEverySupportedRepresentation(): void
    {
        $this->assertSame(1234, new Number("1,234")->value);
        $this->assertSame(123, new Number("0123")->value);
        $this->assertSame(1000.0, new Number("1e3")->value);
        $this->assertSame(-250.0, new Number("-2.5e2")->value);
        $this->assertTrue(new Number(true)->value);

        $number = new Number("12.5");
        $this->assertTrue($number->boolValue);
        $this->assertSame(12.5, $number->floatValue);
        $this->assertSame(12.5, $number->doubleValue);
        $this->assertSame(12, $number->intValue);
        $this->assertSame("12.5", $number->stringValue);
    }

    public function testNumberRejectsNonNumericText(): void
    {
        $this->expectException(AssertionError::class);
        new Number("foundation");
    }

    public function testInternalValueConversionsPreserveNumericTypes(): void
    {
        $object = new stdClass();
        $this->assertSame($object, pv($object));
        $this->assertSame(42, pv(new Value(42)));
        $this->assertSame(42, pn(42));
        $this->assertSame(1.5, pn(1.5));
        $this->assertSame(42, pn("42"));
        $this->assertSame(123, pn("0123"));
        $this->assertSame(1000.0, pn("1e3"));
        $this->assertSame(7, pn(new Number(7)));
        $this->assertSame(1, pn(new Number(true)));
    }

    public function testStringOptionsOnlyApplyRequestedTransformations(): void
    {
        $this->assertSame("Crème brûlée", string_with_options("Crème brûlée", CompareOptions::none));
        $this->assertSame("Crème brûlée", string_with_options("Crème brûlée", CompareOptions::caseInsensitive));
        $this->assertSame("Creme brulee", string_with_options("Crème brûlée", CompareOptions::diacriticInsensitive));
    }
}
