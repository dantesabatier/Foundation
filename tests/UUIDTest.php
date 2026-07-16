<?php

declare(strict_types=1);

namespace Sabatier\Foundation\Tests;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
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

/**
 * Tests for src/UUID.php and src/UUIDAdditions.php.
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
final class UUIDTest extends TestCase
{
    public function testBareConstructorGeneratesAValidV4(): void
    {
        $generated = new UUID();
        $this->assertTrue(uuid_validate($generated->uuidString), "the bare constructor produces a valid UUID string");
        $this->assertSame(36, strlen($generated->uuidString), "the string has the canonical 36-character length");
        $this->assertSame(strtoupper($generated->uuidString), $generated->uuidString, "the generated string is uppercase");
        $this->assertSame("4", $generated->uuidString[14], "the generated UUID is version 4");
        $this->assertContains($generated->uuidString[19], ["8", "9", "A", "B"], "the generated UUID carries the RFC 4122 variant");
        $this->assertFalse(new UUID()->isEqual($generated), "two generated UUIDs are distinct");
    }

    public function testConstructionFromString(): void
    {
        $fromString = new UUID("e621e1f8-c36c-495a-93fc-0c247a3e6e5f");
        $this->assertSame("E621E1F8-C36C-495A-93FC-0C247A3E6E5F", $fromString->uuidString, "lowercase input is normalized to uppercase");
        $this->assertSame($fromString->uuidString, $fromString->description, "description is the uuidString");
        $this->assertSame($fromString->uuidString, $fromString->jsonSerialize(), "jsonSerialize is the uuidString");
        $this->assertStringContainsString("UUID", $fromString->debugDescription, "debugDescription carries the class and the string");
        $this->assertStringContainsString($fromString->uuidString, $fromString->debugDescription, "debugDescription carries the class and the string");
    }

    public function testTheEmptyStringFailsLoudly(): void
    {
        // The empty string is falsy: it used to bypass the validation guard and be stored verbatim.
        $this->expectException(InternalInconsistencyException::class);
        new UUID("");
    }

    /** @return list<array{string}> */
    public static function invalidUuidStrings(): array
    {
        return [
            ["not-a-uuid"],
            ["E621E1F8C36C495A93FC0C247A3E6E5F"],
            ["E621E1F8-C36C-495A-93FC-0C247A3E6E5"],
            ["G621E1F8-C36C-495A-93FC-0C247A3E6E5F"],
        ];
    }

    #[DataProvider("invalidUuidStrings")]
    public function testInvalidInputFailsValidation(string $invalid): void
    {
        $this->expectException(InternalInconsistencyException::class);
        new UUID($invalid);
    }

    public function testEqualityAndComparison(): void
    {
        $upper = new UUID("E621E1F8-C36C-495A-93FC-0C247A3E6E5F");
        $lower = new UUID("e621e1f8-c36c-495a-93fc-0c247a3e6e5f");
        $this->assertTrue($upper->isEqual($lower), "the same UUID given in different case is equal");
        $this->assertSame(ComparisonResult::orderedSame, $upper->compare($lower), "the same UUID compares orderedSame");
        $this->assertFalse($upper->isEqual("E621E1F8-C36C-495A-93FC-0C247A3E6E5F"), "a plain string is never equal");
        $this->assertFalse($upper->isEqual(null), "null is never equal");

        $low = new UUID("00000000-0000-0000-0000-000000000001");
        $high = new UUID("FFFFFFFF-FFFF-FFFF-FFFF-FFFFFFFFFFFF");
        $this->assertSame(ComparisonResult::orderedAscending, $low->compare($high), "a lower UUID compares ascending");
        $this->assertSame(ComparisonResult::orderedDescending, $high->compare($low), "a higher UUID compares descending");
    }

    public function testIdentityVersusConceptualEquality(): void
    {
        // hash is the instance identity (like Number/Date/URL); equality lives in isEqual.
        $upper = new UUID("E621E1F8-C36C-495A-93FC-0C247A3E6E5F");
        $lower = new UUID("e621e1f8-c36c-495a-93fc-0c247a3e6e5f");
        $this->assertNotSame($lower->hash, $upper->hash, "distinct instances keep distinct identity hashes even when equal");
        $this->assertSame(1, new Set([$upper, $lower])->count, "a Set still collapses equal UUID instances through isEqual");
    }

    public function testSerializationRoundTrip(): void
    {
        $original = new UUID("e621e1f8-c36c-495a-93fc-0c247a3e6e5f");
        /** @var UUID $restored */
        $restored = unserialize(serialize($original));
        $this->assertSame("E621E1F8-C36C-495A-93FC-0C247A3E6E5F", $restored->uuidString, "serialization round-trips the normalized string");
        $this->assertTrue($restored->isEqual($original), "the restored UUID equals the original");
    }

    public function testUnserializeValidatesThePayload(): void
    {
        // A tampered payload (same length, invalid characters) must not deserialize.
        $original = new UUID("e621e1f8-c36c-495a-93fc-0c247a3e6e5f");
        $tampered = str_replace($original->uuidString, "ZZZZZZZZ-ZZZZ-ZZZZ-ZZZZ-ZZZZZZZZZZZZ", serialize($original));
        $this->expectException(InternalInconsistencyException::class);
        unserialize($tampered);
    }

    public function testUuidValidate(): void
    {
        $this->assertTrue(uuid_validate("E621E1F8-C36C-495A-93FC-0C247A3E6E5F"), "uuid_validate accepts uppercase");
        $this->assertTrue(uuid_validate("e621e1f8-c36c-495a-93fc-0c247a3e6e5f"), "uuid_validate accepts lowercase");
        $this->assertTrue(uuid_validate(UUID_NULL), "uuid_validate accepts the null UUID");
        $this->assertFalse(uuid_validate(""), "uuid_validate rejects the empty string");
        $this->assertFalse(uuid_validate("E621E1F8C36C495A93FC0C247A3E6E5F"), "uuid_validate rejects a string without dashes");
        $this->assertFalse(uuid_validate("E621E1F8-C36C-495A-93FC-0C247A3E6E5"), "uuid_validate rejects a truncated string");
        $this->assertFalse(uuid_validate("E621E1F8-C36C-495A-93FC-0C247A3E6E5F0"), "uuid_validate rejects trailing characters");
        $this->assertFalse(uuid_validate("G621E1F8-C36C-495A-93FC-0C247A3E6E5F"), "uuid_validate rejects non-hex characters");
    }

    public function testUuidIsNull(): void
    {
        $this->assertTrue(uuid_is_null(UUID_NULL), "uuid_is_null recognizes the null UUID");
        $this->assertFalse(uuid_is_null(uuid_generate()), "uuid_is_null rejects a generated UUID");
    }

    public function testUuidCompare(): void
    {
        $this->assertSame(0, uuid_compare("E621E1F8-C36C-495A-93FC-0C247A3E6E5F", "e621e1f8-c36c-495a-93fc-0c247a3e6e5f"), "uuid_compare is case-insensitive");
        $this->assertLessThan(0, uuid_compare("00000000-0000-0000-0000-000000000000", "FFFFFFFF-FFFF-FFFF-FFFF-FFFFFFFFFFFF"), "uuid_compare orders a lower UUID first");
        $this->assertGreaterThan(0, uuid_compare("FFFFFFFF-FFFF-FFFF-FFFF-FFFFFFFFFFFF", "00000000-0000-0000-0000-000000000000"), "uuid_compare orders a higher UUID last");
    }

    public function testUuidGenerate(): void
    {
        $random = uuid_generate_random();
        $this->assertTrue(uuid_validate($random), "uuid_generate_random produces a valid string");
        $this->assertSame("4", $random[14], "uuid_generate_random sets version 4");
        $this->assertContains(strtolower($random[19]), ["8", "9", "a", "b"], "uuid_generate_random sets the RFC 4122 variant");
        $this->assertTrue(uuid_validate(uuid_generate()), "uuid_generate produces a valid string");
    }

    public function testReadTimeTracksTheWallClock(): void
    {
        // read_time()/nanotime() used to mix unscaled seconds and nanoseconds, so the v1
        // timestamp bore no relation to the clock. Decode it and compare against wall time.
        $gregorianOffset = 0x01B21DD213814000;
        $time = read_time();
        $delta = ($time - $gregorianOffset) / 10_000_000 - microtime(true);
        $this->assertLessThan(5.0, $delta, "read_time tracks the wall clock in 100ns units since 1582");
        $this->assertGreaterThan(-5.0, $delta, "read_time tracks the wall clock in 100ns units since 1582");
        $this->assertGreaterThanOrEqual($time, read_time(), "read_time does not go backwards");
    }

    public function testTimeBasedUuids(): void
    {
        $gregorianOffset = 0x01B21DD213814000;
        $v1 = uuid_generate_time();
        $this->assertTrue(uuid_validate($v1), "uuid_generate_time produces a valid string");
        $this->assertSame("1", $v1[14], "uuid_generate_time sets version 1");
        $this->assertContains(strtolower($v1[19]), ["8", "9", "a", "b"], "uuid_generate_time sets the RFC 4122 variant");

        $hex = str_replace("-", "", $v1);
        $timestamp = ((hexdec(substr($hex, 12, 4)) & 0x0fff) << 48) | (hexdec(substr($hex, 8, 4)) << 32) | hexdec(substr($hex, 0, 8));
        $decoded = ($timestamp - $gregorianOffset) / 10_000_000;
        $this->assertLessThan(5.0, abs($decoded - microtime(true)), "the v1 timestamp decodes to the current wall-clock time");
        $this->assertSame(1, hexdec(substr($hex, 20, 2)) & 0x01, "the random node carries the multicast bit");
    }

    public function testNanotime(): void
    {
        $n1 = nanotime();
        $n2 = nanotime();
        $this->assertGreaterThan(0.0, $n1, "nanotime is positive");
        $this->assertGreaterThanOrEqual($n1, $n2, "nanotime is monotonic");
        $this->assertLessThan(1.0e+9, $n2 - $n1, "consecutive nanotime readings are nanoseconds apart, not scaled garbage");
    }
}
