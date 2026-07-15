<?php

declare(strict_types=1);

namespace Sabatier\Foundation;

use Random\Engine\Secure;
use Random\Randomizer;

/** @var string Represents the null or empty UUID value. */
const UUID_NULL = "00000000-0000-0000-0000-000000000000";

/**
 * Reads a specified number of random bytes.
 *
 * @param int<1, max> $numBytes The number of random bytes to read.
 * @return string A string containing the random bytes.
 */
function read_random(int $numBytes): string
{
    assert($numBytes > 0);
    return new Randomizer(new Secure())->getBytes($numBytes);
}

/**
 * Returns the high-resolution monotonic time in nanoseconds.
 *
 * @return float The monotonic clock reading as a floating-point number of nanoseconds; useful for measuring elapsed time, not wall-clock time.
 */
function nanotime(): float
{
    [$s, $n] = hrtime();
    return ($s * 1.0e+9) + $n;
}

/**
 * Reads the current wall-clock time as an RFC 4122 version 1 UUID timestamp.
 *
 * Integer arithmetic throughout: the value (~2^57) exceeds the 53-bit float mantissa.
 *
 * @return int The number of 100-nanosecond intervals since 00:00:00 UTC on 15 October 1582, the epoch of version 1 UUIDs.
 */
function read_time(): int
{
    [$fraction, $seconds] = explode(" ", microtime());
    return ((int)$seconds * 10_000_000) + (int)((float)$fraction * 1.0e+7) + 0x01B21DD213814000;
}

/**
 * Compares two UUID strings in a case-insensitive manner.
 *
 * @param string $uu1 The first UUID string to compare.
 * @param string $uu2 The second UUID string to compare.
 * @return int Returns 0 if both UUIDs are equal, a value less than 0 if $uu1 is less than $uu2, or a value greater than 0 if $uu1 is greater than $uu2.
 */
function uuid_compare(string $uu1, string $uu2): int
{
    return string_compare($uu1, $uu2, CompareOptions::caseInsensitive);
}

/**
 * Validates whether a given string is a properly formatted UUID.
 *
 * @param string $uuid The UUID string to validate.
 * @return bool Returns true if the input string is a valid UUID, otherwise false.
 */
function uuid_validate(string $uuid): bool
{
    return preg_match("/^[a-f\d]{8}(-[a-f\d]{4}){4}[a-f\d]{8}$/i", $uuid) === 1;
}

/**
 * Generates a random UUID based on RFC 4122 version 4.
 *
 * @return string Returns a randomly generated UUID string in the standard 8-4-4-4-12 hexadecimal format.
 */
function uuid_generate_random(): string
{
    $out = read_random(16);
    $out[6] = chr(ord($out[6]) & 0x0f | 0x40);
    $out[8] = chr(ord($out[8]) & 0x3f | 0x80);
    return $out
            |> bin2hex(...)
            |> (fn(string $x): array => str_split($x, 4))
            |> (fn(array $x): string => vsprintf("%s%s-%s-%s-%s-%s%s%s", $x));
}

/**
 * Generates a version 1 (time-based) UUID string.
 *
 * The UUID is created based on the current time and random data, following
 * the structure defined for version 1 UUIDs.
 *
 * @return string Returns a version 1 UUID as a 36-character string.
 */
function uuid_generate_time(): string
{
    $time = read_time();
    $out = read_random(16);
    $out[0] = chr(($time >> 24) & 0xff);
    $out[1] = chr(($time >> 16) & 0xff);
    $out[2] = chr(($time >> 8) & 0xff);
    $out[3] = chr($time & 0xff);
    $out[4] = chr(($time >> 40) & 0xff);
    $out[5] = chr(($time >> 32) & 0xff);
    $out[6] = chr((($time >> 56) & 0x0f) | 0x10);
    $out[7] = chr(($time >> 48) & 0xff);
    $out[8] = chr((ord($out[8]) & 0x3f) | 0x80);
    // The node is random, not a MAC address; RFC 4122 §4.5 requires the multicast bit in that case.
    $out[10] = chr(ord($out[10]) | 0x01);
    return $out
            |> bin2hex(...)
            |> (fn(string $x): array => str_split($x, 4))
            |> (fn(array $x): string => vsprintf("%s%s-%s-%s-%s-%s%s%s", $x));
}

/**
 * Generates a new random UUID string.
 *
 * @return string Returns a randomly generated UUID string.
 */
function uuid_generate(): string
{
    return uuid_generate_random();
}

/**
 * Checks if a given UUID string is equal to the null UUID.
 *
 * @param string $uuid The UUID string to evaluate.
 * @return bool Returns true if the given UUID is equal to the null UUID, otherwise false.
 */
function uuid_is_null(string $uuid): bool
{
    return $uuid === UUID_NULL;
}
