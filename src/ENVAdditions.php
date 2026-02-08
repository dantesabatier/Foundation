<?php

namespace Sabatier\Foundation;

use SplFileObject;

/**
 * Parses a .env file into an associative array.
 *
 * It handles format "KEY=VALUE", ignores comments starting with "#",
 * and strips optional "export " prefixes. Quoted values (single or double)
 * are unwrapped, but inner spaces are preserved.
 *
 * If the file does not exist or is not readable, an empty array is returned.
 *
 * @param string $path The absolute or relative path to the file.
 * @return array<string, string> An associative array of environment variables.
 */
function parse_env_file(string $path): array
{
    /** @var array<string,string> $environment */
    $environment = [];
    if (!is_file($path) || !is_readable($path)) {
        return $environment;
    }
    $file = new SplFileObject($path);
    $file->setFlags(SplFileObject::DROP_NEW_LINE | SplFileObject::SKIP_EMPTY);
    /** @var string $line */
    foreach ($file as $line) {
        $line = trim($line);
        if ($line === "") {
            continue;
        }
        if (str_starts_with($line, "#")) {
            continue;
        }
        if (str_starts_with($line, "export ")) {
            $line = substr($line, 7);
        }
        if (!str_contains($line, "=")) {
            continue;
        }
        $parts = explode("=", $line, 2);
        if (count($parts) !== 2) {
            continue;
        }
        [$key, $value] = $parts;
        $key = trim($key);
        $value = trim($value);
        $len = strlen($value);
        if ($len >= 2 && ($value[0] === "\"" && $value[$len - 1] === "\"" || $value[0] === "'" && $value[$len - 1] === "'")) {
            $value = substr($value, 1, -1);
        }
        $environment[$key] = $value;
    }
    return $environment;
}
