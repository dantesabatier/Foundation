<?php

declare(strict_types=1);

/**
 * Standalone tests for src/Date.php.
 *
 * Run with: php tests/DateTest.php
 * Exits with a non-zero status code if any check fails.
 *
 * Regression guards:
 *  - timeIntervalSinceReferenceDate is a real CFAbsoluteTime (seconds since 2001-01-01
 *    UTC): absolute_time_get_current() used to return plain Unix time, which made
 *    timeIntervalSince1970 and dateWithTimeIntervalSince1970() shift every date by 31
 *    years (cookie expirations built from numeric timestamps never expired);
 *  - format()/formatted() must convert to Unix time before calling the PHP formatters,
 *    which only worked by accident under the old scheme;
 *  - formatted() must not blow up when IntlDateFormatter::format() fails.
 */

namespace Sabatier\Foundation\Tests;

use Sabatier\Foundation\Date;

require __DIR__ . "/../vendor/autoload.php";

final class DateTestRunner
{
    public static int $passed = 0;
    /** @var list<string> */
    public static array $failures = [];
    private static string $section = "";

    public static function section(string $name): void
    {
        self::$section = $name;
    }

    public static function check(bool $condition, string $message): void
    {
        if ($condition) {
            self::$passed++;
            return;
        }
        $failure = self::$section === "" ? $message : self::$section . ": " . $message;
        self::$failures[] = $failure;
        fwrite(STDERR, "FAIL $failure" . PHP_EOL);
    }

    public static function finish(): never
    {
        $failed = count(self::$failures);
        printf("%d passed, %d failed%s", self::$passed, $failed, PHP_EOL);
        exit($failed > 0 ? 1 : 0);
    }
}

/** Fails the process on any PHP warning/notice. */
set_error_handler(function (int $severity, string $message, string $file, int $line): bool {
    throw new \ErrorException($message, 0, $severity, $file, $line);
});

// format() renders in the process time zone; pin it so the expectations are stable.
date_default_timezone_set("UTC");

$check = DateTestRunner::check(...);
$section = DateTestRunner::section(...);

// ---------------------------------------------------------------------------
$section("reference-date semantics");
// ---------------------------------------------------------------------------

$now = Date::now();
$check(abs($now->timeIntervalSince1970 - microtime(true)) < 2.0, "timeIntervalSince1970 of now matches the system clock");
$check(abs($now->timeIntervalSinceReferenceDate - (microtime(true) - 978_307_200.0)) < 2.0, "timeIntervalSinceReferenceDate is measured from 2001-01-01 UTC");
$check(abs($now->timeIntervalSinceNow) < 2.0, "timeIntervalSinceNow of now is about zero");

$unixEpoch = Date::dateWithTimeIntervalSince1970(0.0);
$check($unixEpoch->timeIntervalSinceReferenceDate === -978_307_200.0, "the Unix epoch is 978307200 seconds before the reference date");
$check($unixEpoch->timeIntervalSince1970 === 0.0, "timeIntervalSince1970 round-trips through the factory");
$check(Date::dateWithTimeIntervalSince1970(1_234_567_890.5)->timeIntervalSince1970 === 1_234_567_890.5, "fractional Unix timestamps survive the round-trip");
$check(new Date(0.0)->timeIntervalSince1970 === 978_307_200.0, "the reference date itself is 978307200 in Unix time");
$check(Date::timeIntervalBetween1970AndReferenceDate === 978_307_200.0, "the 1970-to-reference constant");

// A cookie-style numeric expiration built from Unix time must sit in the near future,
// not 31 years away.
$expiry = Date::dateWithTimeIntervalSince1970((float)time() + 3600.0);
$check($expiry->timeIntervalSinceNow > 3590.0 && $expiry->timeIntervalSinceNow < 3610.0, "a Unix-based expiration one hour out reads as one hour out");

// ---------------------------------------------------------------------------
$section("factories");
// ---------------------------------------------------------------------------

$inAMinute = Date::dateWithTimeIntervalSinceNow(60.0);
$check($inAMinute->timeIntervalSinceNow > 58.0 && $inAMinute->timeIntervalSinceNow < 62.0, "dateWithTimeIntervalSinceNow offsets from now");
$base = Date::dateWithTimeIntervalSinceReferenceDate(1000.0);
$check($base->timeIntervalSinceReferenceDate === 1000.0, "dateWithTimeIntervalSinceReferenceDate stores the interval verbatim");
$check(Date::dateWithTimeIntervalSinceDate(500.0, $base)->timeIntervalSinceReferenceDate === 1500.0, "dateWithTimeIntervalSinceDate offsets from the given date");
$check(Date::distantFuture()->compare(Date::now()) === \Sabatier\Foundation\ComparisonResult::orderedDescending, "distantFuture is after now");
$check(Date::distantPast()->compare(Date::now()) === \Sabatier\Foundation\ComparisonResult::orderedAscending, "distantPast is before now");

// ---------------------------------------------------------------------------
$section("arithmetic and comparison");
// ---------------------------------------------------------------------------

$earlier = Date::dateWithTimeIntervalSinceReferenceDate(100.0);
$later = Date::dateWithTimeIntervalSinceReferenceDate(250.0);
$check($later->timeIntervalSince($earlier) === 150.0, "timeIntervalSince is positive when the receiver is later");
$check($earlier->timeIntervalSince($later) === -150.0, "timeIntervalSince is negative when the receiver is earlier");
$check($earlier->distance($later) === 150.0, "distance is positive toward a later date");
$check($later->distance($earlier) === -150.0, "distance is negative toward an earlier date");

$sum = $earlier->addingTimeInterval(50.0);
$check($sum->timeIntervalSinceReferenceDate === 150.0, "addingTimeInterval adds seconds");
$check($earlier->timeIntervalSinceReferenceDate === 100.0, "addingTimeInterval does not mutate the receiver");
$check($earlier->advanced(25.0)->timeIntervalSinceReferenceDate === 125.0, "advanced is addingTimeInterval");
$mutable = Date::dateWithTimeIntervalSinceReferenceDate(10.0);
$mutable->addTimeInterval(5.0);
$check($mutable->timeIntervalSinceReferenceDate === 15.0, "addTimeInterval mutates in place");

$check($earlier->isEqual(Date::dateWithTimeIntervalSinceReferenceDate(100.0)), "equal instants are equal");
$check(!$earlier->isEqual($later), "different instants are not equal");
$check(!$earlier->isEqual(100.0), "a non-Date value is never equal");
$check($earlier->compare($later) === \Sabatier\Foundation\ComparisonResult::orderedAscending, "earlier compares ascending");

// ---------------------------------------------------------------------------
$section("formatting");
// ---------------------------------------------------------------------------

$check($unixEpoch->format("Y-m-d H:i:s") === "1970-01-01 00:00:00", "format renders the Unix epoch correctly");
$check(new Date(0.0)->format("Y-m-d H:i:s") === "2001-01-01 00:00:00", "format renders the reference date correctly");
$check($unixEpoch->ISO8601Format() === "1970-01-01T00:00:00+00:00", "ISO8601Format renders DATE_ATOM");
$check(new Date(0.0)->description === "2001-01-01 00:00:00", "description is the default format");
$check(new Date(0.0)->jsonSerialize() === "2001-01-01 00:00:00", "jsonSerialize is the description");
$formatted = Date::dateWithTimeIntervalSince1970(0.0)->formatted();
$check(is_string($formatted) && str_contains($formatted, "1970"), "formatted renders the correct year");

// ---------------------------------------------------------------------------
$section("serialization");
// ---------------------------------------------------------------------------

$original = Date::dateWithTimeIntervalSinceReferenceDate(123_456.789);
/** @var Date $restored */
$restored = unserialize(serialize($original));
$check($restored->timeIntervalSinceReferenceDate === 123_456.789, "serialization round-trips the interval");
$check($restored->isEqual($original), "the restored date equals the original");

DateTestRunner::finish();
