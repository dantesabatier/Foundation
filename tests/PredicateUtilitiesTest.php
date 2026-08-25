<?php

declare(strict_types=1);

namespace Sabatier\Foundation\Tests;

use DivisionByZeroError;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Sabatier\Foundation\ArrayClass;
use Sabatier\Foundation\Date;
use Sabatier\Foundation\Number;
use Sabatier\Foundation\Predicates\PredicateUtilities;

/**
 * Tests for src/Predicates/PredicateUtilities.php.
 *
 * Regression guards:
 *  - dateDiff() returned the DateInterval component instead of the elapsed total, so it
 *    answered "days left over after whole years and months" rather than "days between".
 *    From 2020-01-01 to 2022-03-15 it reported 14 days instead of 804, 2 months instead
 *    of 26 and 0 hours instead of 19296; only YEAR happened to agree, because its
 *    component equals the total. CoreData lowers the same operator to MySQL
 *    TIMESTAMPDIFF, which reports totals, so the in-memory and SQL evaluations of one
 *    predicate disagreed: the guarded production expression wrapping
 *    datediff:(SECOND, start, end) silently collapsed to 0 for every interval of a
 *    minute or more, while the SQL path returned the real elapsed seconds. YEAR and
 *    MONTH keep calendar semantics (whole units elapsed) rather than dividing seconds,
 *    matching TIMESTAMPDIFF at the month-length boundaries;
 *  - stddev() built the mean as abs(sum) / count, which mirrors the centre for negative
 *    data and inflates every deviation: [-10,-20,-30] reported 50.0 instead of 10.0.
 *    Positive data is unaffected, which is why it went unnoticed. The sample (n - 1)
 *    divisor is deliberate and unchanged.
 */
final class PredicateUtilitiesTest extends TestCase
{
    protected function setUp(): void
    {
        // The date helpers round-trip through DateTime in the process time zone.
        date_default_timezone_set("UTC");
    }

    /**
     * @return iterable<string, array{string, string, string, int}>
     */
    public static function dateDiffProvider(): iterable
    {
        // Expectations are MySQL TIMESTAMPDIFF(unit, d1, d2) results.
        yield "seconds within the hour" => ["SECOND", "2024-01-01 09:00:00", "2024-01-01 09:30:00", 1800];
        yield "seconds across days" => ["SECOND", "2024-01-01 09:00:00", "2024-01-03 09:00:00", 172800];
        yield "seconds under a minute" => ["SECOND", "2024-01-01 09:00:00", "2024-01-01 09:00:45", 45];
        yield "minutes across the day" => ["MINUTE", "2024-01-01 09:00:00", "2024-01-01 17:00:00", 480];
        yield "hours across years" => ["HOUR", "2020-01-01 00:00:00", "2022-03-15 00:00:00", 19296];
        yield "days across years" => ["DAY", "2020-01-01 00:00:00", "2022-03-15 00:00:00", 804];
        yield "months across years" => ["MONTH", "2020-01-01 00:00:00", "2022-03-15 00:00:00", 26];
        yield "years" => ["YEAR", "2020-01-01 00:00:00", "2022-03-15 00:00:00", 2];
        // Calendar boundaries: a shorter following month does not complete a month, and a day short of the anniversary does not complete a year.
        yield "incomplete month" => ["MONTH", "2024-01-31 00:00:00", "2024-02-29 00:00:00", 0];
        yield "incomplete year" => ["YEAR", "2024-02-29 00:00:00", "2025-02-28 00:00:00", 0];
        yield "negative seconds" => ["SECOND", "2024-01-01 09:00:45", "2024-01-01 09:00:00", -45];
        yield "negative days" => ["DAY", "2022-03-15 00:00:00", "2020-01-01 00:00:00", -804];
    }

    #[DataProvider("dateDiffProvider")]
    public function testDateDiffReportsElapsedTotals(string $unit, string $from, string $to, int $expected): void
    {
        $this->assertSame($expected, PredicateUtilities::dateDiff($unit, $from, $to)->intValue);
    }

    public function testDateDiffIsSymmetricUnderInversion(): void
    {
        $forward = PredicateUtilities::dateDiff("SECOND", "2024-01-01 09:00:00", "2024-01-01 17:00:00")->intValue;
        $backward = PredicateUtilities::dateDiff("SECOND", "2024-01-01 17:00:00", "2024-01-01 09:00:00")->intValue;

        $this->assertSame(28800, $forward);
        $this->assertSame(-28800, $backward);
    }

    public function testDateDiffSurvivesTheGuardedProductionExpression(): void
    {
        // The production expression greatest(ifNull(datediff(SECOND, start, end), 0), 0) collapsed to 0 for every interval of a minute or more while dateDiff returned a component: the guards cannot tell a real zero from a wrong one.
        $elapsed = PredicateUtilities::greatest(
            PredicateUtilities::ifNull(PredicateUtilities::dateDiff("SECOND", "2024-01-01 09:00:00", "2024-01-01 17:00:00"), 0),
            0
        );

        $this->assertSame(28800, $elapsed instanceof Number ? $elapsed->intValue : $elapsed);
    }

    public function testUnknownDateDiffUnitIsZero(): void
    {
        $this->assertSame(0, PredicateUtilities::dateDiff("FORTNIGHT", "2024-01-01", "2024-06-01")->intValue);
    }

    /**
     * @return iterable<string, array{list<int>, float}>
     */
    public static function stddevProvider(): iterable
    {
        // Sample standard deviation (n - 1 divisor), computed independently.
        yield "positive data" => [[2, 4, 4, 4, 5, 5, 7, 9], 2.1380899352993950];
        yield "negative mean" => [[-10, -20, -30], 10.0];
        yield "negative mean, four values" => [[-1, -2, -3, -4], 1.2909944487358056];
        yield "negative pair" => [[-100, -50], 35.35533905932738];
        yield "mean of zero" => [[-5, -3, -1, 1, 3, 5], 3.7416573867739413];
    }

    /**
     * @param list<int> $values
     */
    #[DataProvider("stddevProvider")]
    public function testStddevCentresOnTheSignedMean(array $values, float $expected): void
    {
        $this->assertEqualsWithDelta($expected, PredicateUtilities::stddev(new ArrayClass($values))->floatValue, 1e-9);
    }

    public function testStddevMatchesTheNegatedSample(): void
    {
        // Negating every value mirrors the data about zero and must not change its spread.
        $positive = PredicateUtilities::stddev(new ArrayClass([10, 20, 30]))->floatValue;
        $negative = PredicateUtilities::stddev(new ArrayClass([-10, -20, -30]))->floatValue;

        $this->assertEqualsWithDelta($positive, $negative, 1e-9);
    }

    public function testAggregatesOverAnEmptyCollectionAreNeutral(): void
    {
        $empty = new ArrayClass([]);

        $this->assertSame(0, PredicateUtilities::sum($empty)->intValue);
        $this->assertSame(0, PredicateUtilities::average($empty)->intValue);
        $this->assertSame(0, PredicateUtilities::median($empty)->intValue);
        $this->assertSame(0, PredicateUtilities::stddev($empty)->intValue);
        $this->assertSame(0, PredicateUtilities::mode($empty)->intValue);
        $this->assertNull(PredicateUtilities::min($empty));
        $this->assertNull(PredicateUtilities::max($empty));
    }

    public function testStddevOfASingleValueIsZero(): void
    {
        // The n - 1 divisor would be zero; the implementation must not divide by it.
        $this->assertSame(0, PredicateUtilities::stddev(new ArrayClass([5]))->intValue);
    }

    /**
     * @return iterable<string, array{list<int|float>, float}>
     */
    public static function medianProvider(): iterable
    {
        yield "odd count" => [[1, 2, 3], 2.0];
        yield "even count" => [[1, 2, 3, 4], 2.5];
        yield "unsorted odd" => [[5, 1, 3], 3.0];
        yield "unsorted even" => [[10, 2, 8, 4], 6.0];
    }

    /**
     * @param list<int|float> $values
     */
    #[DataProvider("medianProvider")]
    public function testMedianSortsBeforePickingTheMiddle(array $values, float $expected): void
    {
        $this->assertEqualsWithDelta($expected, PredicateUtilities::median(new ArrayClass($values))->floatValue, 1e-9);
    }

    public function testModeReportsTheMostFrequentValue(): void
    {
        $this->assertSame(2, PredicateUtilities::mode(new ArrayClass([1, 2, 2, 3]))->intValue);
        $this->assertSame(5, PredicateUtilities::mode(new ArrayClass([5, 5, 5, 1, 2]))->intValue);
    }

    public function testAggregatesHandleNegativeValues(): void
    {
        $values = new ArrayClass([-5, -1, -9]);

        $this->assertSame(-9, PredicateUtilities::min($values)?->intValue);
        $this->assertSame(-1, PredicateUtilities::max($values)?->intValue);
        $this->assertSame(-15, PredicateUtilities::sum($values)->intValue);
        $this->assertEqualsWithDelta(-5.0, PredicateUtilities::average($values)->floatValue, 1e-9);
    }

    /**
     * @return iterable<string, array{string, int}>
     */
    public static function dateComponentProvider(): iterable
    {
        // 2021-03-15 14:30:45 UTC, a Monday.
        yield "year" => ["year", 2021];
        yield "month" => ["month", 3];
        yield "day" => ["day", 15];
        yield "hour" => ["hour", 14];
        yield "minute" => ["minute", 30];
        yield "second" => ["second", 45];
        yield "quarter" => ["quarter", 1];
        yield "week" => ["week", 11];
        yield "dayOfWeek" => ["dayOfWeek", 2];
        yield "dayOfYear" => ["dayOfYear", 74];
    }

    #[DataProvider("dateComponentProvider")]
    public function testDateComponentExtractors(string $function, int $expected): void
    {
        $date = Date::dateWithTimeIntervalSince1970((float)mktime(14, 30, 45, 3, 15, 2021));

        $this->assertSame($expected, PredicateUtilities::$function($date)?->intValue);
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function dateComponentNameProvider(): iterable
    {
        foreach (self::dateComponentProvider() as $name => [$function, $_]) {
            yield $name => [$function];
        }
    }

    #[DataProvider("dateComponentNameProvider")]
    public function testDateComponentExtractorsPassNullThrough(string $function): void
    {
        $this->assertNull(PredicateUtilities::$function(null));
    }

    public function testQuarterCoversEveryBoundaryMonth(): void
    {
        foreach ([1 => 1, 3 => 1, 4 => 2, 6 => 2, 7 => 3, 9 => 3, 10 => 4, 12 => 4] as $month => $quarter) {
            $date = Date::dateWithTimeIntervalSince1970((float)mktime(0, 0, 0, $month, 15, 2021));

            $this->assertSame($quarter, PredicateUtilities::quarter($date)?->intValue, "month $month");
        }
    }

    public function testLastDayReturnsTheEndOfTheMonth(): void
    {
        $date = Date::dateWithTimeIntervalSince1970((float)mktime(14, 30, 45, 3, 15, 2021));

        $this->assertSame("2021-03-31", PredicateUtilities::lastDay($date)?->format("Y-m-d"));
        $this->assertNull(PredicateUtilities::lastDay(null));
    }

    public function testLeapYearLastDay(): void
    {
        $leap = Date::dateWithTimeIntervalSince1970((float)mktime(0, 0, 0, 2, 10, 2024));

        $this->assertSame("2024-02-29", PredicateUtilities::lastDay($leap)?->format("Y-m-d"));
    }

    public function testArithmetic(): void
    {
        $this->assertSame(5, PredicateUtilities::addTo(2, 3)->intValue);
        $this->assertSame(6, PredicateUtilities::fromSubtract(10, 4)->intValue);
        $this->assertSame(12, PredicateUtilities::multiplyBy(3, 4)->intValue);
        $this->assertEqualsWithDelta(2.5, PredicateUtilities::divideBy(10, 4)->floatValue, 1e-9);
        $this->assertSame(1, PredicateUtilities::modulusBy(10, 3)->intValue);
        $this->assertSame(1024, PredicateUtilities::raiseToPower(2, 10)->intValue);
        $this->assertSame(4, PredicateUtilities::sqrt(16)->intValue);
        $this->assertSame(7, PredicateUtilities::abs(-7)->intValue);
        $this->assertSame(3, PredicateUtilities::ceiling(2.1)->intValue);
        $this->assertSame(2, PredicateUtilities::log(100)->intValue);
        $this->assertSame(3, PredicateUtilities::log(8, 2)->intValue);
    }

    public function testTruncateDropsTheFractionTowardZero(): void
    {
        $this->assertSame(3, PredicateUtilities::trunc(3.7)->intValue);
        $this->assertSame(-3, PredicateUtilities::trunc(-3.7)->intValue);
        $this->assertSame(3, PredicateUtilities::trunc(3.999)->intValue);
    }

    public function testDivideByZeroRaises(): void
    {
        $this->expectException(DivisionByZeroError::class);

        PredicateUtilities::divideBy(1, 0);
    }

    public function testUnixTimeRoundTrip(): void
    {
        $timestamp = mktime(12, 0, 0, 6, 1, 2022);

        $date = PredicateUtilities::fromUnixTime($timestamp);

        $this->assertSame($timestamp, PredicateUtilities::unixTimestamp($date->format("Y-m-d H:i:s"))->intValue);
    }
}
