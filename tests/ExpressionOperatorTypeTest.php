<?php

declare(strict_types=1);

namespace Sabatier\Foundation\Tests;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Sabatier\Foundation\InternalInconsistencyException;
use Sabatier\Foundation\Predicates\ExpressionOperatorType;

/**
 * Tests the selector-to-operator mapping in src/Predicates/ExpressionOperatorType.php.
 * It is one large match statement, and a wrong arm there routes a predicate function to
 * the wrong implementation — a failure that surfaces as a silently wrong result rather
 * than an error, which is what makes it worth covering exhaustively.
 *
 * Regression guards:
 *  - every documented selector resolves to its operator, including the aliases that
 *    share one: average:/avg:, ceiling:/ceil:;
 *  - the collection accessors first:, last: and size: map to their index operators
 *    rather than to the sequence operators of the same name;
 *  - an unknown selector is a fatal error rather than a silent default.
 */
final class ExpressionOperatorTypeTest extends TestCase
{
    /** @return iterable<string, array{string, ExpressionOperatorType}> */
    public static function aggregateProvider(): iterable
    {
        yield "average" => ["average:", ExpressionOperatorType::average];
        yield "average alias" => ["avg:", ExpressionOperatorType::average];
        yield "sum" => ["sum:", ExpressionOperatorType::sum];
        yield "count" => ["count:", ExpressionOperatorType::count];
        yield "min" => ["min:", ExpressionOperatorType::min];
        yield "max" => ["max:", ExpressionOperatorType::max];
        yield "median" => ["median:", ExpressionOperatorType::median];
        yield "mode" => ["mode:", ExpressionOperatorType::mode];
        yield "standard deviation" => ["stddev:", ExpressionOperatorType::stddev];
    }

    /** @return iterable<string, array{string, ExpressionOperatorType}> */
    public static function arithmeticProvider(): iterable
    {
        yield "add" => ["add:to:", ExpressionOperatorType::addTo];
        yield "subtract" => ["from:subtract:", ExpressionOperatorType::fromSubtract];
        yield "multiply" => ["multiply:by:", ExpressionOperatorType::multiplyBy];
        yield "divide" => ["divide:by:", ExpressionOperatorType::divideBy];
        yield "modulus" => ["modulus:by:", ExpressionOperatorType::modulusBy];
        yield "square root" => ["sqrt:", ExpressionOperatorType::sqrt];
        yield "natural log" => ["ln:", ExpressionOperatorType::ln];
        yield "log" => ["log:", ExpressionOperatorType::log];
        yield "power" => ["raise:toPower:", ExpressionOperatorType::raiseToPower];
        yield "exponent" => ["exp:", ExpressionOperatorType::exp];
        yield "ceiling" => ["ceiling:", ExpressionOperatorType::ceiling];
        yield "ceiling alias" => ["ceil:", ExpressionOperatorType::ceiling];
        yield "absolute" => ["abs:", ExpressionOperatorType::abs];
        yield "truncate" => ["trunc:", ExpressionOperatorType::trunc];
        yield "floor" => ["floor:", ExpressionOperatorType::floor];
        yield "round" => ["round:", ExpressionOperatorType::round];
        yield "random" => ["random:", ExpressionOperatorType::random];
        yield "sign change" => ["chs:", ExpressionOperatorType::chs];
    }

    /** @return iterable<string, array{string, ExpressionOperatorType}> */
    public static function bitwiseProvider(): iterable
    {
        yield "bitwise and" => ["bitwiseAnd:with:", ExpressionOperatorType::bitwiseAndWith];
        yield "bitwise or" => ["bitwiseOr:with:", ExpressionOperatorType::bitwiseOrWith];
        yield "bitwise xor" => ["bitwiseXor:with:", ExpressionOperatorType::bitwiseXorWith];
        yield "bitwise left shift" => ["leftshift:by:", ExpressionOperatorType::leftshiftBy];
        yield "bitwise right shift" => ["rightshift:by:", ExpressionOperatorType::rightshiftBy];
        yield "bitwise complement" => ["onesComplement:", ExpressionOperatorType::onesComplement];
    }

    /** @return iterable<string, array{string, ExpressionOperatorType}> */
    public static function dateProvider(): iterable
    {
        yield "date now" => ["now:", ExpressionOperatorType::now];
        yield "date current date" => ["curdate:", ExpressionOperatorType::currentDate];
        yield "date date" => ["date:", ExpressionOperatorType::date];
        yield "date year" => ["year:", ExpressionOperatorType::year];
        yield "date month" => ["month:", ExpressionOperatorType::month];
        yield "date day" => ["day:", ExpressionOperatorType::day];
        yield "date hour" => ["hour:", ExpressionOperatorType::hour];
        yield "date minute" => ["minute:", ExpressionOperatorType::minute];
        yield "date second" => ["second:", ExpressionOperatorType::second];
        yield "date week" => ["week:", ExpressionOperatorType::week];
        yield "date quarter" => ["quarter:", ExpressionOperatorType::quarter];
        yield "date day of week" => ["dayofweek:", ExpressionOperatorType::dayOfWeek];
        yield "date day of year" => ["dayofyear:", ExpressionOperatorType::dayOfYear];
        yield "date last day" => ["lastday:", ExpressionOperatorType::lastDay];
        yield "date format" => ["date:format:", ExpressionOperatorType::dateFormat];
        yield "date difference" => ["datediff:", ExpressionOperatorType::dateDiff];
        yield "date add" => ["dateadd:", ExpressionOperatorType::dateAdd];
        yield "date subtract" => ["datesub:", ExpressionOperatorType::dateSub];
        yield "date add time" => ["addtime:", ExpressionOperatorType::addTime];
        yield "date subtract time" => ["subtime:", ExpressionOperatorType::subTime];
        yield "date from unix time" => ["fromunixtime:", ExpressionOperatorType::fromUnixTime];
        yield "date unix timestamp" => ["unixtimestamp:", ExpressionOperatorType::unixTimestamp];
    }

    /** @return iterable<string, array{string, ExpressionOperatorType}> */
    public static function stringProvider(): iterable
    {
        yield "string uppercase" => ["uppercase:", ExpressionOperatorType::uppercase];
        yield "string lowercase" => ["lowercase:", ExpressionOperatorType::lowercase];
        yield "string canonical" => ["canonical:", ExpressionOperatorType::canonical];
        yield "string concat" => ["concat:", ExpressionOperatorType::concat];
        yield "string substring" => ["substring:", ExpressionOperatorType::substring];
        yield "string replace" => ["replace:", ExpressionOperatorType::replace];
        yield "string regexp replace" => ["regexpReplace:", ExpressionOperatorType::regexpReplace];
        yield "string length" => ["length:", ExpressionOperatorType::length];
        yield "string trim" => ["trim:", ExpressionOperatorType::trim];
        yield "string left pad" => ["lpad:", ExpressionOperatorType::lpad];
        yield "string right pad" => ["rpad:", ExpressionOperatorType::rpad];
        yield "string left" => ["left:", ExpressionOperatorType::left];
        yield "string right" => ["right:", ExpressionOperatorType::right];
        yield "string index of" => ["instr:", ExpressionOperatorType::instr];
        yield "string reverse" => ["reverse:", ExpressionOperatorType::reverse];
        yield "string repeat" => ["repeat:", ExpressionOperatorType::repeat];
    }

    /** @return iterable<string, array{string, ExpressionOperatorType}> */
    public static function accessorProvider(): iterable
    {
        yield "accessor index" => ["index:", ExpressionOperatorType::index];
        yield "accessor first" => ["first:", ExpressionOperatorType::indexFirst];
        yield "accessor last" => ["last:", ExpressionOperatorType::indexLast];
        yield "accessor size" => ["size:", ExpressionOperatorType::indexSize];
    }

    /** @return iterable<string, array{string, ExpressionOperatorType}> */
    public static function nullHandlingProvider(): iterable
    {
        yield "null is null" => ["isNull:", ExpressionOperatorType::isNull];
        yield "null if null" => ["ifNull:", ExpressionOperatorType::ifNull];
        yield "null null if" => ["nullIf:", ExpressionOperatorType::nullIf];
        yield "null coalesce" => ["coalesce:", ExpressionOperatorType::coalesce];
        yield "null greatest" => ["greatest:", ExpressionOperatorType::greatest];
        yield "null least" => ["least:", ExpressionOperatorType::least];
        yield "null uuid" => ["uuid:", ExpressionOperatorType::uuid];
        yield "null cast" => ["cast:", ExpressionOperatorType::cast];
    }

    #[DataProvider("aggregateProvider")]
    #[DataProvider("arithmeticProvider")]
    #[DataProvider("bitwiseProvider")]
    #[DataProvider("dateProvider")]
    #[DataProvider("stringProvider")]
    #[DataProvider("accessorProvider")]
    #[DataProvider("nullHandlingProvider")]
    public function testASelectorResolvesToItsOperator(string $selector, ExpressionOperatorType $expected): void
    {
        $this->assertSame($expected, ExpressionOperatorType::fromFunctionName($selector));
    }

    /** @return iterable<string, array{string}> */
    public static function unknownSelectorProvider(): iterable
    {
        yield "not a selector" => ["notAFunction"];
        yield "missing colon" => ["sum"];
        yield "empty" => [""];
        yield "wrong case" => ["SUM:"];
    }

    #[DataProvider("unknownSelectorProvider")]
    public function testAnUnknownSelectorIsFatal(string $selector): void
    {
        $this->expectException(InternalInconsistencyException::class);
        $this->expectExceptionMessage("unable to parse selector name");

        ExpressionOperatorType::fromFunctionName($selector);
    }
}
