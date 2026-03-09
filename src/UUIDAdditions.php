<?php

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
 * Returns the high-resolution current time in nanoseconds.
 *
 * @return float The current time as a floating-point number in nanoseconds.
 */
function nanotime(): float
{
    [$s, $n] = hrtime();
    return ($s + $n);
}

/**
 * Reads the current time with high precision and computes it as a float value.
 *
 * @return float The computed time value in nanoseconds as a floating-point number.
 */
function read_time(): float
{
    $time = nanotime();
    return ($time * 1.0e+9) + ($time / 100.0) + (float)0x01B21DD213814000;
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
    $out[0] = chr((int)$time >> 24);
    $out[1] = chr((int)$time >> 16);
    $out[2] = chr((int)$time >> 8);
    $out[3] = chr((int)$time);
    $out[4] = chr((int)$time >> 40);
    $out[5] = chr((int)$time >> 32);
    $out[6] = chr((int)$time >> 56);
    $out[7] = chr((int)$time >> 48);
    $out[6] = chr((ord($out[6]) & 0x0f) | 0x10);
    $out[8] = chr((ord($out[8]) & 0x3f) | 0x80);
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
