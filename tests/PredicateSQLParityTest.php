<?php

declare(strict_types=1);

namespace Sabatier\Foundation\Tests;

use Override;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Sabatier\Foundation\ArrayClass;
use Sabatier\Foundation\Date;
use Sabatier\Foundation\Predicates\Expression;
use Sabatier\Foundation\Predicates\ExpressionOperator;
use Sabatier\Foundation\Predicates\PredicateUtilities;

/**
 * Parity between the in-memory operator functions and the SQL functions CoreData
 * lowers them to, so one predicate cannot answer differently depending on whether it
 * reached the database or fell back to in-memory evaluation.
 *
 * Every expectation below was executed against MariaDB 12.3 and recorded from its
 * output, not from this implementation.
 *
 * Regression guards:
 *  - concat() was declared as concat(string $separator, ArrayClass $arguments), but
 *    ExpressionOperator::expressionValue() spreads the evaluated arguments, so the
 *    second parameter received a plain value and every "concat:" expression with
 *    something to join raised a TypeError. Only the degenerate zero-value call
 *    survived. It is variadic now, and it skips nulls the way CONCAT_WS does rather
 *    than rendering them as empty strings between separators;
 *  - the string functions were typed as plain string, so they raised a TypeError for
 *    the numeric input MariaDB converts implicitly. This was not an edge case:
 *    Expression::expressionForConstantValue("7") normalises to int 7, so any key path
 *    onto a numeric column reached them as int/float and every such expression failed
 *    in memory while succeeding in SQL. They accept string|int|float now and coerce at
 *    the boundary;
 *  - dateDiff() reported DateInterval components instead of elapsed totals, which
 *    disagreed with TIMESTAMPDIFF. See PredicateUtilitiesTest for the full account.
 */
final class PredicateSQLParityTest extends TestCase
{
    #[Override]
    protected function setUp(): void
    {
        // The SQL side ran under time_zone = "+00:00".
        date_default_timezone_set("UTC");
    }

    /**
     * Evaluate through ExpressionOperator so the parity check exercises the same
     * dispatch CoreData falls back to, not a direct call the dispatcher never makes.
     *
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

    /**
     * @return iterable<string, array{string, list<mixed>, string}>
     */
    public static function stringFunctionProvider(): iterable
    {
        // MariaDB: SELECT CONCAT_WS('-','a','b','c') -> a-b-c
        yield "concat" => ["concat:", ["-", "a", "b", "c"], "a-b-c"];
        // CONCAT_WS skips nulls: SELECT CONCAT_WS('-','a',NULL,'c') -> a-c
        yield "concat skips null" => ["concat:", ["-", "a", null, "c"], "a-c"];
        yield "concat single value" => ["concat:", ["-", "a"], "a"];
        // SELECT CONCAT_WS('-',1,2) -> 1-2
        yield "concat casts numbers" => ["concat:", ["-", 1, 2], "1-2"];
        yield "uppercase" => ["uppercase:", ["hola"], "HOLA"];
        yield "lowercase" => ["lowercase:", ["HOLA"], "hola"];
        yield "trim" => ["trim:", ["  x  "], "x"];
        yield "reverse" => ["reverse:", ["abc"], "cba"];
        yield "repeat" => ["repeat:", ["ab", 3], "ababab"];
        yield "left" => ["left:", ["abcdef", 3], "abc"];
        yield "right" => ["right:", ["abcdef", 3], "def"];
        yield "lpad" => ["lpad:", ["7", 3, "0"], "007"];
        yield "rpad" => ["rpad:", ["7", 3, "0"], "700"];
        yield "replace" => ["replace:", ["a-b-c", "-", "+"], "a+b+c"];
    }

    /**
     * @param list<mixed> $arguments
     */
    #[DataProvider("stringFunctionProvider")]
    public function testStringFunctionsMatchMariaDB(string $operator, array $arguments, string $expected): void
    {
        $this->assertSame($expected, (string)$this->evaluate($operator, $arguments));
    }

    /**
     * @return iterable<string, array{string, list<mixed>, string}>
     */
    public static function numericCoercionProvider(): iterable
    {
        // MariaDB converts numbers to text implicitly in its string functions, and Expression::expressionForConstantValue() normalises "7" to int 7, so a key path onto a numeric column reaches these functions as int/float. Verified: SELECT UPPER(1), LENGTH(123), REVERSE(123), LPAD(7,3,'0'), ...
        yield "uppercase" => ["uppercase:", [1], "1"];
        yield "lowercase" => ["lowercase:", [1], "1"];
        yield "length" => ["length:", [123], "3"];
        yield "reverse" => ["reverse:", [123], "321"];
        yield "trim" => ["trim:", [5], "5"];
        yield "lpad" => ["lpad:", [7, 3, "0"], "007"];
        yield "rpad" => ["rpad:", [7, 3, "0"], "700"];
        yield "left" => ["left:", [12345, 3], "123"];
        yield "right" => ["right:", [12345, 3], "345"];
        yield "repeat" => ["repeat:", [12, 2], "1212"];
        yield "replace" => ["replace:", [121, 1, 9], "929"];
        yield "instr" => ["instr:", [12345, 34], "3"];
        yield "substring" => ["substring:", [12345, 2], "2345"];
    }

    /**
     * @param list<mixed> $arguments
     */
    #[DataProvider("numericCoercionProvider")]
    public function testStringFunctionsAcceptNumbersLikeMariaDB(string $operator, array $arguments, string $expected): void
    {
        $this->assertSame($expected, (string)$this->evaluate($operator, $arguments));
    }

    public function testConcatWithNothingToJoinIsEmpty(): void
    {
        // MariaDB rejects CONCAT_WS with no values at all; the in-memory fallback has no parser to reject it, so it must at least not raise.
        $this->assertSame("", (string)$this->evaluate("concat:", ["-"]));
    }

    /**
     * @return iterable<string, array{string, list<mixed>, int|float}>
     */
    public static function numericFunctionProvider(): iterable
    {
        yield "abs" => ["abs:", [-7], 7];
        yield "ceiling" => ["ceiling:", [2.1], 3];
        yield "floor" => ["floor:", [2.9], 2];
        yield "sqrt" => ["sqrt:", [16], 4];
        yield "exp" => ["exp:", [0], 1];
        yield "ln" => ["ln:", [1], 0];
        // MariaDB LOG(base, value): SELECT LOG(10,100) -> 2
        yield "log" => ["log:", [100, 10], 2];
        yield "power" => ["raise:toPower:", [2, 10], 1024];
        yield "greatest" => ["greatest:", [3, 9, 5], 9];
        yield "least" => ["least:", [3, 9, 5], 3];
        yield "instr" => ["instr:", ["hello", "ll"], 3];
        yield "length" => ["length:", ["hello"], 5];
        yield "round" => ["round:", [2.567, 2], 2.57];
        yield "truncate" => ["trunc:", [3.789], 3];
    }

    /**
     * @param list<mixed> $arguments
     */
    #[DataProvider("numericFunctionProvider")]
    public function testNumericFunctionsMatchMariaDB(string $operator, array $arguments, int|float $expected): void
    {
        $result = $this->evaluate($operator, $arguments);

        $this->assertEqualsWithDelta($expected, is_object($result) ? $result->floatValue : $result, 1e-9);
    }

    /**
     * @return iterable<string, array{string, list<mixed>, int}>
     */
    public static function dateDiffParityProvider(): iterable
    {
        // SELECT TIMESTAMPDIFF(unit, d1, d2) under time_zone = "+00:00".
        yield "seconds" => ["datediff:", ["SECOND", "2024-01-01 09:00:00", "2024-01-01 17:00:00"], 28800];
        yield "days" => ["datediff:", ["DAY", "2020-01-01 00:00:00", "2022-03-15 00:00:00"], 804];
        yield "months" => ["datediff:", ["MONTH", "2020-01-01 00:00:00", "2022-03-15 00:00:00"], 26];
        yield "years" => ["datediff:", ["YEAR", "2020-01-01 00:00:00", "2022-03-15 00:00:00"], 2];
    }

    /**
     * @param list<mixed> $arguments
     */
    #[DataProvider("dateDiffParityProvider")]
    public function testDateDiffMatchesTimestampDiff(string $operator, array $arguments, int $expected): void
    {
        $this->assertSame($expected, $this->evaluate($operator, $arguments)->intValue);
    }

    /**
     * @return iterable<string, array{string, string}>
     */
    public static function dateTimeFunctionProvider(): iterable
    {
        // SELECT ADDTIME('2024-01-01 10:00:00','02:30:00') -> 2024-01-01 12:30:00
        yield "addtime" => ["addtime:", "2024-01-01 12:30:00"];
        // SELECT SUBTIME('2024-01-01 10:00:00','02:30:00') -> 2024-01-01 07:30:00
        yield "subtime" => ["subtime:", "2024-01-01 07:30:00"];
    }

    #[DataProvider("dateTimeFunctionProvider")]
    public function testTimeArithmeticMatchesMariaDB(string $operator, string $expected): void
    {
        $result = $this->evaluate($operator, ["2024-01-01 10:00:00", "02:30:00"]);

        $this->assertSame($expected, $result->format("Y-m-d H:i:s"));
    }

    public function testUnixTimestampMatchesMariaDB(): void
    {
        // SELECT UNIX_TIMESTAMP('2022-06-01 12:00:00') under +00:00 -> 1654084800
        $this->assertSame(1654084800, PredicateUtilities::unixTimestamp("2022-06-01 12:00:00")->intValue);
    }

    /**
     * @return iterable<string, array{string, int}>
     */
    public static function dateComponentParityProvider(): iterable
    {
        // Components of 2021-03-15, verified against MariaDB.
        yield "year" => ["year", 2021];
        yield "month" => ["month", 3];
        yield "day" => ["day", 15];
        yield "quarter" => ["quarter", 1];
        // SELECT WEEK('2021-03-15', 3) -> 11, matching PHP's ISO-8601 "W".
        yield "week" => ["week", 11];
        // SELECT DAYOFWEEK('2021-03-15') -> 2 (Sunday is 1).
        yield "dayOfWeek" => ["dayOfWeek", 2];
        // SELECT DAYOFYEAR('2021-03-15') -> 74.
        yield "dayOfYear" => ["dayOfYear", 74];
    }

    #[DataProvider("dateComponentParityProvider")]
    public function testDateComponentsMatchMariaDB(string $function, int $expected): void
    {
        $date = Date::dateWithTimeIntervalSince1970((float)mktime(0, 0, 0, 3, 15, 2021));

        $this->assertSame($expected, PredicateUtilities::$function($date)?->intValue);
    }

    public function testLastDayMatchesMariaDB(): void
    {
        // SELECT LAST_DAY('2021-03-15') -> 2021-03-31
        $date = Date::dateWithTimeIntervalSince1970((float)mktime(0, 0, 0, 3, 15, 2021));

        $this->assertSame("2021-03-31", PredicateUtilities::lastDay($date)?->format("Y-m-d"));
    }

    /**
     * @return iterable<string, array{list<int>, string, float}>
     */
    public static function aggregateParityProvider(): iterable
    {
        yield "sum" => [[1, 2, 3], "sum:", 6.0];
        yield "average" => [[1, 2, 3], "average:", 2.0];
        yield "min" => [[5, 1, 9], "min:", 1.0];
        yield "max" => [[5, 1, 9], "max:", 9.0];
        // SELECT STDDEV_SAMP(v) over 2,4,4,4,5,5,7,9 -> 2.1381
        yield "stddev" => [[2, 4, 4, 4, 5, 5, 7, 9], "stddev:", 2.1380899352993950];
    }

    /**
     * @param list<int> $values
     */
    #[DataProvider("aggregateParityProvider")]
    public function testAggregatesMatchMariaDB(array $values, string $operator, float $expected): void
    {
        $result = $this->evaluate($operator, [new ArrayClass($values)]);

        $this->assertEqualsWithDelta($expected, $result->floatValue, 1e-9);
    }

    /**
     * @return iterable<string, array{string, list<mixed>, mixed}>
     */
    public static function nullFunctionProvider(): iterable
    {
        // SELECT COALESCE(NULL,NULL,'x') -> x
        yield "coalesce" => ["coalesce:", [null, null, "x"], "x"];
        // SELECT IFNULL(NULL,'d') -> d
        yield "ifnull" => ["ifNull:", [null, "d"], "d"];
        // SELECT NULLIF(5,5) -> NULL
        yield "nullif equal" => ["nullIf:", [5, 5], null];
    }

    /**
     * @param list<mixed> $arguments
     */
    #[DataProvider("nullFunctionProvider")]
    public function testNullHandlingMatchesMariaDB(string $operator, array $arguments, mixed $expected): void
    {
        $result = $this->evaluate($operator, $arguments);

        $this->assertSame($expected, is_object($result) ? (string)$result : $result);
    }
}
