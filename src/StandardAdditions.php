<?php

declare(strict_types=1);

namespace Sabatier\Foundation;

use Collator;
use JetBrains\PhpStorm\Deprecated;
use JetBrains\PhpStorm\ExpectedValues;
use JetBrains\PhpStorm\Pure;
use SensitiveParameter;

/**
 * Returns the current system absolute time, measured in seconds since 00:00:00 UTC on 1 January 2001.
 * This is the PHP counterpart of CFAbsoluteTimeGetCurrent().
 * @return float The current absolute time.
 */
function absolute_time_get_current(): float
{
    return microtime(true) - kCFAbsoluteTimeIntervalSince1970;
}

/**
 * Generates a deterministic color from a string, suitable for use in UI elements depending on the theme (dark or light).
 *
 * The same input string always produces the same color. The function ensures that the resulting
 * color has enough contrast to be legible on the specified background theme.
 *
 * @param string $string The input string used to derive the color.
 * @param string $theme The target UI theme. Acceptable values are:
 *                      - "dark": generates lighter colors suitable for dark backgrounds.
 *                      - "light": generates darker colors suitable for light backgrounds.
 * @return string A CSS-compatible hexadecimal color string, e.g., "#F57C00".
 *
 * <code>
 * // Generate a color for dark theme
 * $color = random_color("hello", "dark"); // e.g., "#F57C00"
 * </code>
 *
 * <code>
 * // Generate a color for light theme
 * $color = random_color("hello", "light"); // e.g., "#1976D2"
 * </code>
 */
function random_color(string $string, string $theme = "dark"): string
{
    $hash = crc32(strtolower($string));
    $h = abs($hash) % 360;
    $s = 80;
    $l = $theme === "dark" ? 50 : 30;
    return hsl_to_hex($h, $s, $l);
}

function hsl_to_hex(int $h, int $s, int $l): string
{
    $s /= 100;
    $l /= 100;
    $c = (1 - abs(2 * $l - 1)) * $s;
    $x = $c * (1 - abs(fmod($h / 60, 2) - 1));
    $m = $l - $c / 2;
    if ($h < 60) {
        [$r, $g, $b] = [$c, $x, 0];
    } elseif ($h < 120) {
        [$r, $g, $b] = [$x, $c, 0];
    } elseif ($h < 180) {
        [$r, $g, $b] = [0, $c, $x];
    } elseif ($h < 240) {
        [$r, $g, $b] = [0, $x, $c];
    } elseif ($h < 300) {
        [$r, $g, $b] = [$x, 0, $c];
    } else {
        [$r, $g, $b] = [$c, 0, $x];
    }
    $r = round(($r + $m) * 255);
    $g = round(($g + $m) * 255);
    $b = round(($b + $m) * 255);
    return sprintf("#%02X%02X%02X", $r, $g, $b);
}

/**
 * Determines whether a value falls within the specified half-open range [min, max).
 * The minimum is inclusive and the maximum is exclusive.
 *
 * @param float|int|string $value The value to check.
 * @param float|int|string $min The inclusive lower bound of the range.
 * @param float|int|string $max The exclusive upper bound of the range.
 * @return bool Returns true if min <= value < max, otherwise false.
 */
#[Pure]
function in_range(float|int|string $value, float|int|string $min, float|int|string $max): bool
{
    return $max > $min && $value >= $min && $value < $max;
}

/**
 * Determines whether the given array has an integer or null first key.
 * An array is considered sequential if it is empty or if its first key is an integer.
 * Note: this does not verify that keys are consecutive starting from 0; use array_is_list() for that.
 *
 * @param array $array The array to check.
 * @return bool Returns true if the array is empty or its first key is an integer, otherwise false.
 */
#[Pure]
function is_sequential(array $array): bool
{
    $key = array_key_first($array);
    return is_null($key) || is_int($key);
}

/**
 * Removes the first occurrence of a specified element from the array and re-indexes if necessary.
 *
 * @param array $array The reference to the array from which the element will be removed.
 * @param mixed $element The element to be removed from the array.
 * @return array The modified array after removing the specified element.
 */
function array_remove(array &$array, mixed $element): array
{
    $index = array_search($element, $array, true);
    if ($index !== false) {
        unset($array[$index]);
        if (is_sequential($array)) {
            $array = array_values($array);
        }
    }
    return $array;
}

/**
 * Splits a string by a separator and returns an array of non-empty, trimmed components.
 *
 * This function divides the input string using the specified separator, trims
 * whitespace from each component, and removes any empty results.
 *
 * It is intended for parsing simple delimited strings such as configuration
 * values or environment variables.
 *
 * @param string $string The string to split.
 * @param non-empty-string $separator The delimiter used to split the string. Defaults to ",".
 *
 * @return array<int, string> An array of trimmed, non-empty components.
 */
function string_split_trimmed(string $string, string $separator = ","): array
{
    return explode($separator, $string)
            |> (fn(array $x): array => array_map(trim(...), $x))
            |> (fn(array $x): array => array_filter($x, fn(string $v): bool => $v !== ""))
            |> array_values(...);
}

/**
 * Extracts a substring from the given string starting at the specified index.
 *
 * @param string $string The original string from which the substring is extracted.
 * @param int $index The starting index for the substring.
 * @return string Returns the substring starting from the given index in the original string.
 */
#[Pure]
function substring_from_index(string $string, int $index): string
{
    return substr($string, $index);
}

/**
 * Returns a substring from the beginning of the given string up to the specified index.
 * @param string $string The input string from which the substring will be extracted.
 * @param int $index The zero-based index indicating the length of the substring to extract.
 * @return string The resulting substring up to the specified index.
 */
#[Pure]
function substring_to_index(string $string, int $index): string
{
    return substr($string, 0, $index);
}

/**
 * Converts the given string to its canonical form by applying case-insensitive and diacritic-insensitive transformations.
 *
 * @param string $string The input string to be converted.
 * @return string The canonicalized version of the input string.
 */
function canonical(string $string): string
{
    return string_with_options($string, CompareOptions::caseInsensitive | CompareOptions::diacriticInsensitive);
}

/**
 * Converts the given string to a lowercase, ASCII slug by transliterating diacritics and collapsing every run of
 * non-alphanumeric characters into a single separator. Leading and trailing separators are trimmed.
 *
 * @param string $string The input string to be slugified.
 * @param string $separator The separator used to join words. Defaults to a hyphen.
 * @return string The slugified version of the input string, or an empty string if it contains no alphanumeric characters.
 */
function slug(string $string, string $separator = "-"): string
{
    return strtolower(canonical($string))
            |> (fn(string $x): string => (string)preg_replace("/[^a-z0-9]+/", $separator, $x))
            |> (fn(string $x): string => trim($x, $separator));
}

/**
 * Converts a string to camel case by capitalizing the first letter of each word after a separator
 * (space, underscore, or hyphen) and removing the separators.
 * @param string $string The input string to be converted to camel case.
 * @return string Returns the camel case representation of the input string.
 */
function camelcase(string $string): string
{
    return (string)preg_replace_callback("/[_\-\s](.)/", fn(array $matches) => strtoupper($matches[1]), $string);
}

/**
 * Capitalizes the first character of every word in a UTF-8 encoded string, preserving the remaining characters of each word as-is.
 *
 * @param string $string The input string whose words will be capitalized.
 * @return string The input string with each word capitalized.
 */
function capitalize(string $string): string
{
    return mb_convert_case($string, MB_CASE_TITLE, "UTF-8");
}

/**
 * Checks if the string contains a given substring.
 * @param string $string The string to process.
 * @param string $substring The substring to search for.
 * @param int $options The comparison mask to use or none for a strict string comparison.
 * @return bool True if string contains substring; otherwise false.
 */
function in_string(string $string, string $substring, #[ExpectedValues(flagsFromClass: CompareOptions::class)] int $options = CompareOptions::none): bool
{
    if ($options !== CompareOptions::none) {
        $string = string_with_options($string, $options);
        $substring = string_with_options($substring, $options);
        if ($options & CompareOptions::caseInsensitive) {
            return stripos($string, $substring) !== false;
        }
    }
    return str_contains($string, $substring);
}

/**
 * Compares the string with the specified string using the given options.
 * @param string $string The receiver string.
 * @param string $other The string with which to compare.
 * @param int $options Options for the comparison, you can combine any of the {@see CompareOptions} using a C bitwise OR operator.
 * @return int Returns an int value that indicates the lexical ordering.
 */
function string_compare(string $string, string $other, #[ExpectedValues(flagsFromClass: CompareOptions::class)] int $options = CompareOptions::none): int
{
    static $collator = null;
    if ($options !== CompareOptions::none) {
        if (class_exists("Collator")) {
            $collator ??= Collator::create("root");
            if ($collator) {
                $collator->setAttribute(Collator::STRENGTH, Collator::PRIMARY);
                if (!($options & CompareOptions::diacriticInsensitive)) {
                    $collator->setAttribute(Collator::STRENGTH, Collator::SECONDARY);
                }
                if (!($options & CompareOptions::caseInsensitive)) {
                    $collator->setAttribute(Collator::STRENGTH, Collator::TERTIARY);
                }
                if (($options & CompareOptions::caseInsensitive) && ($options & CompareOptions::diacriticInsensitive) && !($options & CompareOptions::normalized)) {
                    $collator->setAttribute(Collator::NORMALIZATION_MODE, Collator::ON);
                } else {
                    $collator->setAttribute(Collator::NORMALIZATION_MODE, Collator::OFF);
                }
                return $collator->compare($string, $other)
                        |> (fn(int $x): int => min($x, ComparisonResult::orderedDescending->value))
                        |> (fn(int $x): int => max($x, ComparisonResult::orderedAscending->value));
            }
        }
        $string = string_with_options($string, $options);
        $other = string_with_options($other, $options);
        if ($options & CompareOptions::caseInsensitive) {
            return strcasecmp($string, $other)
                    |> (fn(int $x): int => min($x, ComparisonResult::orderedDescending->value))
                    |> (fn(int $x): int => max($x, ComparisonResult::orderedAscending->value));
        }
    }
    return strcmp($string, $other)
            |> (fn(int $x): int => min($x, ComparisonResult::orderedDescending->value))
            |> (fn(int $x): int => max($x, ComparisonResult::orderedAscending->value));
}

/**
 * Checks if two strings are equal using the given options.
 * @param string $string The receiver string.
 * @param string $other The string with which to compare.
 * @param int $options The options for the comparison.
 * @return bool true if the strings are lexically equals.
 */
function string_is_equal(string $string, string $other, #[ExpectedValues(flagsFromClass: CompareOptions::class)] int $options = CompareOptions::none): bool
{
    return string_compare($string, $other, $options) === ComparisonResult::orderedSame->value;
}

/**
 * Returns a Boolean value indicating whether the initial characters of the string are the same as the characters in $prefix.
 * @param string $string The receiver string.
 * @param string $prefix The string with which to compare.
 * @param int $options The options for the comparison.
 * @return bool true if the initial characters of the string are the same as the characters of $prefix; otherwise, false.
 */
function string_has_prefix(string $string, string $prefix, #[ExpectedValues(flagsFromClass: CompareOptions::class)] int $options = CompareOptions::none): bool
{
    return $prefix
            |> strlen(...)
            |> (fn(int $x): string => substring_to_index($string, $x))
            |> (fn(string $x): bool => string_is_equal($x, $prefix, $options));
}

/**
 * Returns a Boolean value indicating whether the final characters of the string are the same as the characters in suffix.
 * @param string $string The receiver string.
 * @param string $suffix The string with which to compare.
 * @param int $options The options for the comparison.
 * @return bool true if the final characters of the string are the same as the characters of suffix; otherwise, false.
 */
function string_has_suffix(string $string, string $suffix, #[ExpectedValues(flagsFromClass: CompareOptions::class)] int $options = CompareOptions::none): bool
{
    return string_is_equal(substring_from_index($string, strlen($string) - strlen($suffix)), $suffix, $options);
}

/**
 * Searches for a given string using a regex-style comparison according to {@link http://userguide.icu-project.org/strings/regexp ICU v3}.
 * @param string $string The input string.
 * @param string $needle The string to search for.
 * @param SearchMethod $method The search method
 * @param int $options The options for the comparison.
 * @param array|null $matches Array of all matches
 * @return int Returns the number of matches.
 */
function string_search(string $string, string $needle, SearchMethod $method = SearchMethod::matches, #[ExpectedValues(flagsFromClass: CompareOptions::class)] int $options = CompareOptions::none, ?array &$matches = null): int
{
    $string = string_with_options($string, $options);
    $needle = string_with_options($needle, $options);
    if (!($options & CompareOptions::quoted)) {
        $needle = preg_quote($needle, "/");
    }
    /** @var non-empty-string $pattern */
    $pattern = $needle;
    /** @noinspection PhpSuspiciousNameCombinationInspection */
    if (!str_starts_with($needle, "/") && !str_ends_with($needle, "/")) {
        $pattern = "/";
        $pattern .= match ($method) {
            SearchMethod::matches => "^$needle$",
            SearchMethod::beginsWith => "^$needle",
            SearchMethod::endsWith => "$needle$",
            SearchMethod::contains => ($options & CompareOptions::words) ? "(?:^|\W)$needle(?:$|\W)" : $needle
        };
        $pattern .= "/";
        if ($options & CompareOptions::caseInsensitive) {
            $pattern .= "i";
        }
    }
    $value = preg_match_all($pattern, $string, $matches);
    if ($value === false) {
        $matches = [];
        return 0;
    }
    $matches = array_map(trim(...), $matches[0]);
    return $value;
}

/**
 * Checks if the string matches the specified other string using the given options.
 * @param string $string The receiver string.
 * @param string $other The string to check against.
 * @param int $options Options for the string matching, you can combine any of the {@see CompareOptions} using a C bitwise OR operator.
 * @return bool Returns true if the string matches the specified other string according to the options, otherwise false.
 */
function string_matches(string $string, string $other, #[ExpectedValues(flagsFromClass: CompareOptions::class)] int $options = CompareOptions::none): bool
{
    return string_search($string, $other, SearchMethod::matches, $options) > 0;
}

/**
 * Determines if the string begins with the specified substring using the given options.
 *
 * @param string $string The receiver string.
 * @param string $other The substring to check for at the beginning of the string.
 * @param int $options Options for the comparison, you can combine any of the {@see CompareOptions} using a C bitwise OR operator.
 * @return bool Returns true if the string begins with the specified substring; otherwise, false.
 */
function string_begins_with(string $string, string $other, #[ExpectedValues(flagsFromClass: CompareOptions::class)] int $options = CompareOptions::none): bool
{
    return string_search($string, $other, SearchMethod::beginsWith, $options) > 0;
}

/**
 * Determines if the string ends with the specified substring using the given options.
 *
 * @param string $string The string to analyze.
 * @param string $other The substring to check against the end of the string.
 * @param int $options Options for the comparison, you can combine any of the {@see CompareOptions} using a C bitwise OR operator.
 * @return bool Returns true if the string ends with the specified substring, otherwise false.
 */
function string_ends_with(string $string, string $other, #[ExpectedValues(flagsFromClass: CompareOptions::class)] int $options = CompareOptions::none): bool
{
    return string_search($string, $other, SearchMethod::endsWith, $options) > 0;
}

/**
 * Checks if a string contains a specified substring using the given options.
 * @param string $string The string to search within.
 * @param string $substring The substring to search for.
 * @param int $options Options for the search, you can combine any of the {@see CompareOptions} using a C bitwise OR operator.
 * @return bool Returns true if the substring is found in the string, false otherwise.
 */
function string_contains(string $string, string $substring, #[ExpectedValues(flagsFromClass: CompareOptions::class)] int $options = CompareOptions::none): bool
{
    return string_search($string, $substring, SearchMethod::contains, $options) > 0;
}

/**
 * Checks if the given string contains only ASCII characters.
 *
 * @param string $string The string to check.
 * @return bool Returns true if the string contains only ASCII characters, otherwise false.
 */
function is_ascii(string $string): bool
{
    return mb_check_encoding($string, "ASCII");
}

/**
 * Returns a localized version of the string designated by the specified key and residing in the specified table.
 * @param string $string The key for a string in the specified table.
 * @param string $domain The name of the table containing the key-value pairs.
 * @param string $directory The directory containing the .strings file.
 * @param string $comment The comment to place above the key-value pair in the .strings file.
 * @return string The localized string.
 * @noinspection PhpUnusedParameterInspection
 */
function localized_string(string $string, string $domain = "Localizable", string $directory = "", string $comment = ""): string
{
    static $cache = [];
    $fileManager = FileManager::default();
    if ($directory !== "" && $fileManager->fileExists($directory, $isDirectory) && $isDirectory) {
        $directoryURL = URL::fileURL($directory);
        $bundleURL = $directoryURL->deletingLastPathComponent();
        $resourcesURL = $bundleURL->appendingPathComponent("Resources");
    } else {
        $resourcesURL = null;
        $trace = debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS, 2);
        if (isset($trace[1]["file"])) {
            $fileURL = URL::fileURL($trace[1]["file"]);
            $dirURL = $fileURL->deletingLastPathComponent();
            if ($dirURL->lastPathComponent === "src") {
                $bundleURL = $dirURL->deletingLastPathComponent();
                $resourcesURL = $bundleURL->appendingPathComponent("Resources");
            }
        }
        if ($resourcesURL === null) {
            $frameworks = new Set(Bundle::allFrameworks());
            $frameworks->insert(Bundle::bundleForClass(FileManager::class));
            foreach ($frameworks as $bundle) {
                $potentialURL = $bundle->bundleURL->appendingPathComponent("Resources");
                if ($fileManager->fileExists($potentialURL->path, $isDirectory) && $isDirectory) {
                    $resourcesURL = $potentialURL;
                    break;
                }
            }
        }
        $resourcesURL ??= Bundle::main()->bundleURL->appendingPathComponent("Resources");
    }
    $key = "$domain|$resourcesURL->path";
    if (!isset($cache[$key])) {
        $cache[$key] = true;
        bindtextdomain($domain, $resourcesURL->path);
        bind_textdomain_codeset($domain, "UTF-8");
    }
    textdomain($domain);
    return gettext($string);
}

/**
 * Encodes the given string into a Base64 URL-safe format.
 * @param string $string The input string to encode.
 * @return string Returns the Base64 URL-encoded representation of the input string.
 */
function base64_url_encode(string $string): string
{
    return $string
            |> base64_encode(...)
            |> (fn(string $x): string => strtr($x, "+/", "-_"))
            |> (fn(string $x): string => rtrim($x, "="));
}

/**
 * Retrieves the document root directory of the server environment.
 * It detects the document root based on the server configuration or
 * working directory if executed from the command line interface.
 * @return string The document root directory path.
 */
function document_root_directory(): string
{
    $path = $_SERVER["DOCUMENT_ROOT"] ?? "";
    if (RUNNING_FROM_CLI) {
        if (isset($_SERVER["PWD"])) {
            $path = $_SERVER["PWD"];
        }
        if (empty($path)) {
            $path = getcwd() ?: "";
        }
    }
    return $path;
}

/**
 * Retrieves the current user's home directory path.
 *
 * @return string Returns the path to the current user's home directory.
 */
function home_directory(): string
{
    if ($path = getenv("HOME")) {
        return rtrim($path, "/");
    }
    /** @noinspection SpellCheckingInspection */
    if (!empty($_SERVER["HOMEDRIVE"]) && !empty($_SERVER["HOMEPATH"])) {
        /** @noinspection SpellCheckingInspection */
        return rtrim($_SERVER["HOMEDRIVE"] . $_SERVER["HOMEPATH"], "\\/");
    }
    fatal_error("failed to get current user directory");
}

/**
 * A string containing the account name of the current user.
 */
function user_name(): string
{
    if (function_exists("posix_getpwuid")) {
        /** @noinspection PhpComposerExtensionStubsInspection */
        $name = posix_getpwuid(posix_geteuid())["name"] ?? "";
        if ($name !== "") {
            return $name;
        }
    }
    return getenv("USERNAME") ?: getenv("USER") ?: get_current_user();
}

/**
 * A string containing the full name of the current user.
 */
function full_user_name(): string
{
    if (function_exists("posix_getpwuid")) {
        /** @noinspection PhpComposerExtensionStubsInspection */
        $gecos = posix_getpwuid(posix_geteuid())["gecos"] ?? "";
        return explode(",", $gecos)[0] ?: user_name();
    }
    if (TARGET_OS_WINDOWS && function_exists("shell_exec")) {
        $output = user_name()
                |> escapeshellarg(...)
                |> (fn(string $x): string => sprintf("net user %s 2>nul", $x))
                |> shell_exec(...);
        if ($output && preg_match("/(?:Full Name|Nombre completo)\\s+(.+)/i", $output, $matches)) {
            return trim($matches[1]);
        }
    }
    return user_name();
}

/**
 * Returns the name of the current process.
 */
function process_name(): string
{
    if (RUNNING_FROM_CLI) {
        return cli_get_process_title() ?: "Unknown";
    }
    return "Unknown";
}

/**
 * Returns the path of the temporary directory for the current user.
 * @return string A string containing the path of the temporary directory for the current user.
 */
function temporary_directory(): string
{
    return sys_get_temp_dir();
}

/**
 * Determines whether the specified file is hidden.
 *
 * @param string $filename The name of the file to check.
 * @return bool Returns true if the file is hidden; otherwise, false.
 */
function is_hidden(string $filename): bool
{
    if (USE_UNSAFE_FUNCTIONS && TARGET_OS_WINDOWS && function_exists("shell_exec")) {
        $attributes = trim(unsafe_value(fn(): string => $filename
                |> escapeshellarg(...)
                |> (fn(string $x): string => sprintf("FOR %%A IN (%s) DO @ECHO %%~aA", $x))
                |> shell_exec(...)));
        if (strlen($attributes) >= 5) {
            return $attributes[3] === "h" || $attributes[4] === "s";
        }
    }
    return str_starts_with($filename, ".");
}

/**
 * Determines if the given value is a serialized string.
 *
 * @param mixed $value The value to be evaluated.
 * @param bool $strict Whether to perform strict checking for valid serialized strings. If true, the function evaluates the exact syntax of the serialized string more rigorously; if false, the evaluation is less strict.
 * @return bool Returns true if the given value is a serialized string; otherwise, false.
 */
function is_serialized(mixed $value, bool $strict = true): bool
{
    if (!is_string($value)) {
        return false;
    }
    if ("N;" === $value) {
        return true;
    }
    if (strlen($value) < 4) {
        return false;
    }
    if (":" !== $value[1]) {
        return false;
    }
    if ($strict) {
        $last = substr($value, -1);
        if (";" !== $last && "}" !== $last) {
            return false;
        }
    } else {
        $semicolon = strpos($value, ";");
        $brace = strpos($value, "}");
        if (false === $semicolon && false === $brace) {
            return false;
        }
        if (false !== $semicolon && $semicolon < 3) {
            return false;
        }
        if (false !== $brace && $brace < 4) {
            return false;
        }
    }
    $token = $value[0];
    switch ($token) {
        case "s":
            if ($strict) {
                if (substr($value, -2, 1) !== "\"") {
                    return false;
                }
                if (preg_match("/^s:(\d+):\"/", $value, $m)) {
                    $contentStart = strlen($m[0]);
                    $contentEnd = strrpos($value, "\";");
                    return $contentEnd !== false && (int)$m[1] === $contentEnd - $contentStart;
                }
                return false;
            }
            if (!str_contains($value, "\"")) {
                return false;
            }
        // no break
        case "a":
        case "O":
            return (bool)preg_match("/^$token:\d+:/s", $value);
        case "b":
            $end = $strict ? "$" : "";
            return (bool)preg_match("/^b:[01];$end/", $value);
        case "i":
            $end = $strict ? "$" : "";
            return (bool)preg_match("/^i:-?\d+;$end/", $value);
        case "d":
            $end = $strict ? "$" : "";
            return (bool)preg_match("/^d:(?:[\d.E+-]+|-?INF|NAN);$end/", $value);
    }
    return false;
}

/**
 * Determines whether the given string is a password hash generated by PHP's `password_hash()` function.
 *
 * This allows distinguishing between:
 *  - legacy plain-text or custom hashes → false
 *  - modern password_hash() values → true
 *
 * @param non-empty-string $string The value to inspect
 * @return bool
 *
 * @see password_get_info()
 * @see password_hash()
 * @see password_verify()
 */
#[Deprecated]
function is_password(#[SensitiveParameter] string $string): bool
{
    return !empty(password_get_info($string)["algo"]);
}

/**
 * Determines if two values are equal.
 *
 * @param mixed $a The first value to compare. This can be of any type.
 * @param mixed $b The second value to compare. This can be of any type.
 * @return bool Returns true if the values are considered equal, false otherwise.
 */
function is_equal(mixed $a, mixed $b): bool
{
    if ($a instanceof Equatable) {
        return $a->isEqual($b);
    }
    if ($b instanceof Equatable) {
        return $b->isEqual($a);
    }
    if ($a === $b) {
        return true;
    }
    if ((is_int($a) || is_float($a)) && (is_int($b) || is_float($b))) {
        return $a == $b;
    }
    return false;
}

/**
 * Compares two values to determine their ordering.
 * If the receiver implements {@see Comparable}, it decides what it can be compared with.
 * If only the other value implements it, the result is negated.
 * If neither implements it, the spaceship operator is used.
 *
 * @param mixed $a The first value to compare.
 * @param mixed $b The second value to compare.
 * @return int Returns a negative integer, zero, or a positive integer as $a is less than, equal to, or greater than $b.
 */
function compare(mixed $a, mixed $b): int
{
    if ($a instanceof Comparable) {
        return $a->compare($b)->value;
    }
    if ($b instanceof Comparable) {
        return -$b->compare($a)->value;
    }
    return $a <=> $b;
}
