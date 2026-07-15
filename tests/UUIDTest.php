<?php

declare(strict_types=1);

/**
 * Standalone tests for src/UUID.php and src/UUIDAdditions.php.
 *
 * Run with: php tests/UUIDTest.php
 * Exits with a non-zero status code if any check fails.
 *
 * Regression guards:
 *  - new UUID("") must fail loudly: the empty string used to slip past the truthiness
 *    guard and become the stored uuidString;
 *  - uuidString is normalized to uppercase, matching NSUUID, regardless of input case;
 *  - hash stays the inherited instance identity: like Number/Date/URL, UUID expresses
 *    conceptual equality through isEqual/compare only, so equal values collapse in Set
 *    without sharing a hash;
 *  - __unserialize validates its payload instead of accepting any string;
 *  - uuid_generate_time() encodes the actual wall-clock time: read_time()/nanotime()
 *    used to mix unscaled seconds and nanoseconds, producing garbage timestamps.
 */

namespace Sabatier\Foundation\Tests;

use Sabatier\Foundation\ComparisonResult;
use Sabatier\Foundation\InternalInconsistencyException;
use Sabatier\Foundation\Set;
use Sabatier\Foundation\UUID;

use function Sabatier\Foundation\nanotime;
use function Sabatier\Foundation\read_time;
use function Sabatier\Foundation\uuid_compare;
use function Sabatier\Foundation\uuid_generate;
use function Sabatier\Foundation\uuid_generate_random;
use function Sabatier\Foundation\uuid_generate_time;
use function Sabatier\Foundation\uuid_is_null;
use function Sabatier\Foundation\uuid_validate;

use const Sabatier\Foundation\UUID_NULL;

require __DIR__ . "/../vendor/autoload.php";

final class UUIDTestRunner
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

$check = UUIDTestRunner::check(...);
$section = UUIDTestRunner::section(...);

// ---------------------------------------------------------------------------
$section("construction");
// ---------------------------------------------------------------------------

$generated = new UUID();
$check(uuid_validate($generated->uuidString), "the bare constructor produces a valid UUID string");
$check(strlen($generated->uuidString) === 36, "the string has the canonical 36-character length");
$check($generated->uuidString === strtoupper($generated->uuidString), "the generated string is uppercase");
$check($generated->uuidString[14] === "4", "the generated UUID is version 4");
$check(in_array($generated->uuidString[19], ["8", "9", "A", "B"], true), "the generated UUID carries the RFC 4122 variant");
$check(!(new UUID())->isEqual($generated), "two generated UUIDs are distinct");

$fromString = new UUID("e621e1f8-c36c-495a-93fc-0c247a3e6e5f");
$check($fromString->uuidString === "E621E1F8-C36C-495A-93FC-0C247A3E6E5F", "lowercase input is normalized to uppercase");
$check($fromString->description === $fromString->uuidString, "description is the uuidString");
$check($fromString->jsonSerialize() === $fromString->uuidString, "jsonSerialize is the uuidString");
$check(str_contains($fromString->debugDescription, "UUID") && str_contains($fromString->debugDescription, $fromString->uuidString), "debugDescription carries the class and the string");

// ---------------------------------------------------------------------------
$section("invalid input");
// ---------------------------------------------------------------------------

// The empty string is falsy: it used to bypass the validation guard and be stored verbatim.
try {
    new UUID("");
    $check(false, "the empty string must fail");
} catch (InternalInconsistencyException) {
    $check(true, "the empty string fails loudly instead of becoming the uuidString");
}
foreach (["not-a-uuid", "E621E1F8C36C495A93FC0C247A3E6E5F", "E621E1F8-C36C-495A-93FC-0C247A3E6E5", "G621E1F8-C36C-495A-93FC-0C247A3E6E5F"] as $invalid) {
    try {
        new UUID($invalid);
        $check(false, "\"$invalid\" must fail");
    } catch (InternalInconsistencyException) {
        $check(true, "\"$invalid\" fails validation");
    }
}

// ---------------------------------------------------------------------------
$section("equality and comparison");
// ---------------------------------------------------------------------------

$upper = new UUID("E621E1F8-C36C-495A-93FC-0C247A3E6E5F");
$lower = new UUID("e621e1f8-c36c-495a-93fc-0c247a3e6e5f");
$check($upper->isEqual($lower), "the same UUID given in different case is equal");
$check($upper->compare($lower) === ComparisonResult::orderedSame, "the same UUID compares orderedSame");
$check(!$upper->isEqual("E621E1F8-C36C-495A-93FC-0C247A3E6E5F"), "a plain string is never equal");
$check(!$upper->isEqual(null), "null is never equal");

$low = new UUID("00000000-0000-0000-0000-000000000001");
$high = new UUID("FFFFFFFF-FFFF-FFFF-FFFF-FFFFFFFFFFFF");
$check($low->compare($high) === ComparisonResult::orderedAscending, "a lower UUID compares ascending");
$check($high->compare($low) === ComparisonResult::orderedDescending, "a higher UUID compares descending");

// ---------------------------------------------------------------------------
$section("identity versus conceptual equality");
// ---------------------------------------------------------------------------

// hash is the instance identity (like Number/Date/URL); equality lives in isEqual.
$check($upper->hash !== $lower->hash, "distinct instances keep distinct identity hashes even when equal");
$check(new Set([$upper, $lower])->count === 1, "a Set still collapses equal UUID instances through isEqual");

// ---------------------------------------------------------------------------
$section("serialization");
// ---------------------------------------------------------------------------

$original = new UUID("e621e1f8-c36c-495a-93fc-0c247a3e6e5f");
/** @var UUID $restored */
$restored = unserialize(serialize($original));
$check($restored->uuidString === "E621E1F8-C36C-495A-93FC-0C247A3E6E5F", "serialization round-trips the normalized string");
$check($restored->isEqual($original), "the restored UUID equals the original");

// A tampered payload (same length, invalid characters) must not deserialize.
$tampered = str_replace($original->uuidString, "ZZZZZZZZ-ZZZZ-ZZZZ-ZZZZ-ZZZZZZZZZZZZ", serialize($original));
try {
    unserialize($tampered);
    $check(false, "a tampered payload must fail");
} catch (InternalInconsistencyException) {
    $check(true, "__unserialize validates the payload");
}

// ---------------------------------------------------------------------------
$section("helper functions");
// ---------------------------------------------------------------------------

$check(uuid_validate("E621E1F8-C36C-495A-93FC-0C247A3E6E5F"), "uuid_validate accepts uppercase");
$check(uuid_validate("e621e1f8-c36c-495a-93fc-0c247a3e6e5f"), "uuid_validate accepts lowercase");
$check(uuid_validate(UUID_NULL), "uuid_validate accepts the null UUID");
$check(!uuid_validate(""), "uuid_validate rejects the empty string");
$check(!uuid_validate("E621E1F8C36C495A93FC0C247A3E6E5F"), "uuid_validate rejects a string without dashes");
$check(!uuid_validate("E621E1F8-C36C-495A-93FC-0C247A3E6E5"), "uuid_validate rejects a truncated string");
$check(!uuid_validate("E621E1F8-C36C-495A-93FC-0C247A3E6E5F0"), "uuid_validate rejects trailing characters");
$check(!uuid_validate("G621E1F8-C36C-495A-93FC-0C247A3E6E5F"), "uuid_validate rejects non-hex characters");

$check(uuid_is_null(UUID_NULL), "uuid_is_null recognizes the null UUID");
$check(!uuid_is_null(uuid_generate()), "uuid_is_null rejects a generated UUID");

$check(uuid_compare("E621E1F8-C36C-495A-93FC-0C247A3E6E5F", "e621e1f8-c36c-495a-93fc-0c247a3e6e5f") === 0, "uuid_compare is case-insensitive");
$check(uuid_compare("00000000-0000-0000-0000-000000000000", "FFFFFFFF-FFFF-FFFF-FFFF-FFFFFFFFFFFF") < 0, "uuid_compare orders a lower UUID first");
$check(uuid_compare("FFFFFFFF-FFFF-FFFF-FFFF-FFFFFFFFFFFF", "00000000-0000-0000-0000-000000000000") > 0, "uuid_compare orders a higher UUID last");

$random = uuid_generate_random();
$check(uuid_validate($random), "uuid_generate_random produces a valid string");
$check($random[14] === "4", "uuid_generate_random sets version 4");
$check(in_array(strtolower($random[19]), ["8", "9", "a", "b"], true), "uuid_generate_random sets the RFC 4122 variant");
$check(uuid_validate(uuid_generate()), "uuid_generate produces a valid string");

// ---------------------------------------------------------------------------
$section("time-based UUIDs");
// ---------------------------------------------------------------------------

// read_time()/nanotime() used to mix unscaled seconds and nanoseconds, so the v1
// timestamp bore no relation to the clock. Decode it and compare against wall time.
$gregorianOffset = 0x01B21DD213814000;
$time = read_time();
$check(($time - $gregorianOffset) / 10_000_000 - microtime(true) < 5.0 && ($time - $gregorianOffset) / 10_000_000 - microtime(true) > -5.0, "read_time tracks the wall clock in 100ns units since 1582");
$check(read_time() >= $time, "read_time does not go backwards");

$v1 = uuid_generate_time();
$check(uuid_validate($v1), "uuid_generate_time produces a valid string");
$check($v1[14] === "1", "uuid_generate_time sets version 1");
$check(in_array(strtolower($v1[19]), ["8", "9", "a", "b"], true), "uuid_generate_time sets the RFC 4122 variant");

$hex = str_replace("-", "", $v1);
$timestamp = ((hexdec(substr($hex, 12, 4)) & 0x0fff) << 48) | (hexdec(substr($hex, 8, 4)) << 32) | hexdec(substr($hex, 0, 8));
$decoded = ($timestamp - $gregorianOffset) / 10_000_000;
$check(abs($decoded - microtime(true)) < 5.0, "the v1 timestamp decodes to the current wall-clock time");
$check((hexdec(substr($hex, 20, 2)) & 0x01) === 1, "the random node carries the multicast bit");

$n1 = nanotime();
$n2 = nanotime();
$check($n1 > 0.0, "nanotime is positive");
$check($n2 >= $n1, "nanotime is monotonic");
$check($n2 - $n1 < 1.0e+9, "consecutive nanotime readings are nanoseconds apart, not scaled garbage");

UUIDTestRunner::finish();
