<?php

declare(strict_types=1);

namespace Sabatier\Foundation\Tests;

use Override;
use PHPUnit\Framework\TestCase;
use Sabatier\Foundation\ComparisonResult;
use Sabatier\Foundation\Date;
use Sabatier\Foundation\InternalInconsistencyException;

/**
 * Tests for src/Date.php.
 *
 * Regression guards:
 *  - timeIntervalSinceReferenceDate is a real CFAbsoluteTime (seconds since 2001-01-01
 *    UTC): absolute_time_get_current() used to return plain Unix time, which made
 *    timeIntervalSince1970 and dateWithTimeIntervalSince1970() shift every date by 31
 *    years (cookie expirations built from numeric timestamps never expired);
 *  - the constructor takes no arguments and fails loudly on any, because PHP silently
 *    ignores extra arguments to non-variadic functions; value-based construction goes
 *    through the epoch-named factories;
 *  - format()/formatted() must convert to Unix time before calling the PHP formatters,
 *    which only worked by accident under the old scheme.
 */
final class DateTest extends TestCase
{
    #[Override]
    protected function setUp(): void
    {
        // format() renders in the process time zone; pin it so the expectations are stable.
        date_default_timezone_set("UTC");
    }

    public function testNowTracksTheSystemClock(): void
    {
        $now = Date::now();
        $this->assertLessThan(2.0, abs($now->timeIntervalSince1970 - microtime(true)), "timeIntervalSince1970 of now matches the system clock");
        $this->assertLessThan(2.0, abs($now->timeIntervalSinceReferenceDate - (microtime(true) - 978_307_200.0)), "timeIntervalSinceReferenceDate is measured from 2001-01-01 UTC");
        $this->assertLessThan(2.0, abs($now->timeIntervalSinceNow), "timeIntervalSinceNow of now is about zero");
    }

    public function testUnixEpochConversions(): void
    {
        $unixEpoch = Date::dateWithTimeIntervalSince1970(0.0);
        $this->assertSame(-978_307_200.0, $unixEpoch->timeIntervalSinceReferenceDate, "the Unix epoch is 978307200 seconds before the reference date");
        $this->assertSame(0.0, $unixEpoch->timeIntervalSince1970, "timeIntervalSince1970 round-trips through the factory");
        $this->assertSame(1_234_567_890.5, Date::dateWithTimeIntervalSince1970(1_234_567_890.5)->timeIntervalSince1970, "fractional Unix timestamps survive the round-trip");
        $this->assertSame(978_307_200.0, Date::dateWithTimeIntervalSinceReferenceDate(0.0)->timeIntervalSince1970, "the reference date itself is 978307200 in Unix time");
        $this->assertSame(978_307_200.0, Date::timeIntervalBetween1970AndReferenceDate, "the 1970-to-reference constant");
    }

    public function testBareConstructorIsTheCurrentInstant(): void
    {
        $this->assertLessThan(2.0, abs(new Date()->timeIntervalSinceNow), "the bare constructor is the current instant");
    }

    public function testConstructorArgumentsFailLoudly(): void
    {
        // PHP silently ignores extra arguments to non-variadic functions, so the constructor promotes them to a hard error instead of silently meaning "now" — the old `new Date($timestamp)` habit fails loudly.
        $this->expectException(InternalInconsistencyException::class);
        new Date(529_887_685.0);
    }

    public function testUnixFactoryFormatting(): void
    {
        $this->assertSame("2024-02-10 15:30:00", Date::dateWithTimeIntervalSince1970((float)strtotime("2024-02-10 15:30:00"))->format("Y-m-d H:i:s"), "the 1970 factory round-trips through format");
        $this->assertSame("1986-10-16", Date::dateWithTimeIntervalSince1970(529_887_685.0)->format("Y-m-d"), "a literal Unix timestamp lands on its calendar date");
    }

    public function testCookieStyleExpirationSitsInTheNearFuture(): void
    {
        // A cookie-style numeric expiration built from Unix time must sit in the near future, not 31 years away.
        $expiry = Date::dateWithTimeIntervalSince1970((float)time() + 3600.0);
        $this->assertGreaterThan(3590.0, $expiry->timeIntervalSinceNow, "a Unix-based expiration one hour out reads as one hour out");
        $this->assertLessThan(3610.0, $expiry->timeIntervalSinceNow, "a Unix-based expiration one hour out is not in the far future");
    }

    public function testFactories(): void
    {
        $inAMinute = Date::dateWithTimeIntervalSinceNow(60.0);
        $this->assertGreaterThan(58.0, $inAMinute->timeIntervalSinceNow, "dateWithTimeIntervalSinceNow offsets from now");
        $this->assertLessThan(62.0, $inAMinute->timeIntervalSinceNow, "dateWithTimeIntervalSinceNow does not overshoot");
        $base = Date::dateWithTimeIntervalSinceReferenceDate(1000.0);
        $this->assertSame(1000.0, $base->timeIntervalSinceReferenceDate, "dateWithTimeIntervalSinceReferenceDate stores the interval verbatim");
        $this->assertSame(1500.0, Date::dateWithTimeIntervalSinceDate(500.0, $base)->timeIntervalSinceReferenceDate, "dateWithTimeIntervalSinceDate offsets from the given date");
    }

    public function testDistantDates(): void
    {
        $this->assertSame(ComparisonResult::orderedDescending, Date::distantFuture()->compare(Date::now()), "distantFuture is after now");
        $this->assertSame(ComparisonResult::orderedAscending, Date::distantPast()->compare(Date::now()), "distantPast is before now");
    }

    public function testIntervalArithmetic(): void
    {
        $earlier = Date::dateWithTimeIntervalSinceReferenceDate(100.0);
        $later = Date::dateWithTimeIntervalSinceReferenceDate(250.0);
        $this->assertSame(150.0, $later->timeIntervalSince($earlier), "timeIntervalSince is positive when the receiver is later");
        $this->assertSame(-150.0, $earlier->timeIntervalSince($later), "timeIntervalSince is negative when the receiver is earlier");
        $this->assertSame(150.0, $earlier->distance($later), "distance is positive toward a later date");
        $this->assertSame(-150.0, $later->distance($earlier), "distance is negative toward an earlier date");
    }

    public function testMutationSemantics(): void
    {
        $earlier = Date::dateWithTimeIntervalSinceReferenceDate(100.0);
        $sum = $earlier->addingTimeInterval(50.0);
        $this->assertSame(150.0, $sum->timeIntervalSinceReferenceDate, "addingTimeInterval adds seconds");
        $this->assertSame(100.0, $earlier->timeIntervalSinceReferenceDate, "addingTimeInterval does not mutate the receiver");
        $this->assertSame(125.0, $earlier->advanced(25.0)->timeIntervalSinceReferenceDate, "advanced is addingTimeInterval");
        $mutable = Date::dateWithTimeIntervalSinceReferenceDate(10.0);
        $mutable->addTimeInterval(5.0);
        $this->assertSame(15.0, $mutable->timeIntervalSinceReferenceDate, "addTimeInterval mutates in place");
    }

    public function testEqualityAndComparison(): void
    {
        $earlier = Date::dateWithTimeIntervalSinceReferenceDate(100.0);
        $later = Date::dateWithTimeIntervalSinceReferenceDate(250.0);
        $this->assertTrue($earlier->isEqual(Date::dateWithTimeIntervalSinceReferenceDate(100.0)), "equal instants are equal");
        $this->assertFalse($earlier->isEqual($later), "different instants are not equal");
        $this->assertFalse($earlier->isEqual(100.0), "a non-Date value is never equal");
        $this->assertSame(ComparisonResult::orderedAscending, $earlier->compare($later), "earlier compares ascending");
    }

    public function testFormatting(): void
    {
        $unixEpoch = Date::dateWithTimeIntervalSince1970(0.0);
        $this->assertSame("1970-01-01 00:00:00", $unixEpoch->format("Y-m-d H:i:s"), "format renders the Unix epoch correctly");
        $this->assertSame("2001-01-01 00:00:00", Date::dateWithTimeIntervalSinceReferenceDate(0.0)->format("Y-m-d H:i:s"), "format renders the reference date correctly");
        $this->assertSame("1970-01-01T00:00:00+00:00", $unixEpoch->ISO8601Format(), "ISO8601Format renders DATE_ATOM");
        $this->assertSame("2001-01-01 00:00:00", Date::dateWithTimeIntervalSinceReferenceDate(0.0)->description, "description is the default format");
        $this->assertSame("2001-01-01 00:00:00", Date::dateWithTimeIntervalSinceReferenceDate(0.0)->jsonSerialize(), "jsonSerialize is the description");
        $this->assertStringContainsString("1970", Date::dateWithTimeIntervalSince1970(0.0)->formatted(), "formatted renders the correct year");
    }

    public function testSerialization(): void
    {
        $original = Date::dateWithTimeIntervalSinceReferenceDate(123_456.789);
        /** @var Date $restored */
        $restored = unserialize(serialize($original));
        $this->assertSame(123_456.789, $restored->timeIntervalSinceReferenceDate, "serialization round-trips the interval");
        $this->assertTrue($restored->isEqual($original), "the restored date equals the original");
    }
}
