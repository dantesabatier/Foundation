<?php

namespace Sabatier\Foundation;

use Random\Engine\Secure;
use Random\Randomizer;

const UUID_NULL = "00000000-0000-0000-0000-000000000000";

/**
 * @param int<1, max> $numBytes
 * @return string
 */
function read_random(int $numBytes): string
{
    return new Randomizer(new Secure())->getBytes($numBytes);
}

function nanotime(): float
{
    [$s, $n] = hrtime();
    return ($s + $n);
}

function read_time(): float
{
    $time = nanotime();
    return ($time * 1.0e+9) + ($time / 100) + 0x01B21DD213814000;
}

function uuid_compare(string $uu1, string $uu2): int
{
    return string_compare($uu1, $uu2, CompareOptions::caseInsensitive);
}

function uuid_validate(string $uuid): bool
{
    return preg_match("/^[a-f\d]{8}(-[a-f\d]{4}){4}[a-f\d]{8}$/i", $uuid) === 1;
}

function uuid_generate_random(): string
{
    $out = read_random(16);
    $out[6] = chr(ord($out[6]) & 0x0f | 0x40);
    $out[8] = chr(ord($out[8]) & 0x3f | 0x80);
    return vsprintf("%s%s-%s-%s-%s-%s%s%s", str_split(bin2hex($out), 4));
}

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
    return vsprintf("%s%s-%s-%s-%s-%s%s%s", str_split(bin2hex($out), 4));
}

function uuid_generate(): string
{
    return uuid_generate_random();
}

function uuid_is_null(string $uuid): bool
{
    return $uuid === UUID_NULL;
}
