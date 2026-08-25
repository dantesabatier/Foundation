<?php

declare(strict_types=1);

namespace Sabatier\Foundation\Tests;

use InvalidArgumentException;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use ReflectionMethod;
use Sabatier\Foundation\ArrayClass;
use Sabatier\Foundation\Date;
use Sabatier\Foundation\Predicates\Expression;
use Sabatier\Foundation\Predicates\ExpressionOperator;
use Sabatier\Foundation\Predicates\ExpressionOperatorType;
use Sabatier\Foundation\Predicates\PredicateUtilities;

/**
 * Tests for src/Predicates/ExpressionOperator.php and the PredicateUtilities
 * functions it dispatches to.
 *
 * Regression guards:
 *  - expressionValue() dispatches statically through a dynamic method name
 *    (PredicateUtilities::$selector(...)), so every function reachable from
 *    ExpressionOperatorType must be declared static. dateAdd() and dateSub() were the
 *    only two instance methods on the class, which made every "dateadd:"/"datesub:"
 *    expression raise "Non-static method cannot be called statically" at runtime.
 *    Static analysis cannot see this: the selector is resolved from the enum case name
 *    at call time, so only an end-to-end evaluation catches it.
 */
final class ExpressionOperatorTest extends TestCase
{
    protected function setUp(): void
    {
        // The date helpers round-trip through DateTime/Date in the process time zone; pin it so the expectations are stable.
        date_default_timezone_set("UTC");
    }

    /**
     * @param list<mixed> $arguments
     */
    private function evaluate(string $name, array $arguments): mixed
    {
        $expressions = new ArrayClass(array_map(
            static fn(mixed $argument): Expression => Expression::expressionForConstantValue($argument),
            $arguments
        ));
        return ExpressionOperator::operatorWithName($name, $expressions)->expressionValue();
    }

    public function testEveryOperatorFunctionIsStatic(): void
    {
        foreach (ExpressionOperatorType::cases() as $case) {
            $method = new ReflectionMethod(PredicateUtilities::class, $case->name);
            $this->assertTrue(
                $method->isStatic(),
                "PredicateUtilities::{$case->name}() must be static: ExpressionOperator dispatches to it statically."
            );
        }
    }

    public function testDateAddEvaluatesThroughTheOperator(): void
    {
        $result = $this->evaluate("dateadd:", ["2020-03-15 10:30:00", 2, "DAY"]);

        $this->assertInstanceOf(Date::class, $result);
        $this->assertSame("2020-03-17 10:30:00", $result->description);
    }

    public function testDateSubEvaluatesThroughTheOperator(): void
    {
        $result = $this->evaluate("datesub:", ["2020-03-15 10:30:00", 2, "DAY"]);

        $this->assertInstanceOf(Date::class, $result);
        $this->assertSame("2020-03-13 10:30:00", $result->description);
    }

    /**
     * @return iterable<string, array{string, string, string}>
     */
    public static function dateUnitProvider(): iterable
    {
        yield "year" => ["YEAR", "2022-03-15 10:30:00", "2018-03-15 10:30:00"];
        yield "month" => ["MONTH", "2020-05-15 10:30:00", "2020-01-15 10:30:00"];
        yield "day" => ["DAY", "2020-03-17 10:30:00", "2020-03-13 10:30:00"];
        yield "hour" => ["HOUR", "2020-03-15 12:30:00", "2020-03-15 08:30:00"];
        yield "minute" => ["MINUTE", "2020-03-15 10:32:00", "2020-03-15 10:28:00"];
        yield "second" => ["SECOND", "2020-03-15 10:30:02", "2020-03-15 10:29:58"];
    }

    #[DataProvider("dateUnitProvider")]
    public function testDateAddAndSubHandleEveryUnit(string $unit, string $added, string $subtracted): void
    {
        $this->assertSame($added, $this->evaluate("dateadd:", ["2020-03-15 10:30:00", 2, $unit])->description);
        $this->assertSame($subtracted, $this->evaluate("datesub:", ["2020-03-15 10:30:00", 2, $unit])->description);
    }

    public function testUnknownUnitIsRejected(): void
    {
        $this->expectException(InvalidArgumentException::class);

        $this->evaluate("dateadd:", ["2020-03-15 10:30:00", 1, "FORTNIGHT"]);
    }
}
