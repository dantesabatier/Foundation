<?php

namespace Sabatier\Foundation;

use Collator;
use Exception;
use JetBrains\PhpStorm\ExpectedValues;
use JetBrains\PhpStorm\Pure;
use RuntimeException;

/**
 * Returns the current system absolute time.
 * @return float The current absolute time.
 */
function absolute_time_get_current(): float
{
    $tv = gettimeofday();
    return $tv['sec'] + (1.0e-6 * (float)$tv['usec']);
}

#[Pure]
function in_range(float|int|string $value, float|int|string $min, float|int|string $max): bool
{
    return $max > $min && $value >= $min && $value < $max;
}

#[Pure]
function is_sequential(array $array): bool
{
    $key = array_key_first($array);
    return is_null($key) || is_int($key);
}

/**
 * @template Index of array-key
 * @template Element
 * @param callable(mixed, mixed=, bool=): bool $predicate
 * @param array<Index, Element> $array
 * @param FilteringMethod $method
 * @return array<Index, Element>
 * @psalm-suppress InvalidReturnType, InvalidReturnStatement, TypeDoesNotContainType
 */
function array_passing_test(callable $predicate, array $array, FilteringMethod $method = FilteringMethod::default): array
{
    $elements = [];
    $sequential = is_sequential($array);
    foreach ($array as $key => $value) {
        $stop = false;
        if (match ($method) {
            FilteringMethod::useKey => $predicate($key, $stop),
            FilteringMethod::useValue => $predicate($value, $stop),
            default => $predicate($value, $key, $stop)
        }) {
            if ($sequential) {
                $elements[] = $value;
            } else {
                $elements[$key] = $value;
            }
        }
        if ($stop) { // @phpstan-ignore-line
            break;
        }
    }
    return $elements;
}

/**
 * @template Index of array-key
 * @template Element
 * @param array<Index, Element> $array
 * @param callable(mixed, mixed=, bool=): bool|null $predicate
 * @param FilteringMethod $method
 * @return Element|null
 */
function array_first(array $array, callable $predicate = null, FilteringMethod $method = FilteringMethod::default)
{
    $key = array_key_first($array);
    if ($key === null) {
        return null;
    }
    if ($predicate === null) {
        return $array[$key];
    }
    $array = array_passing_test(function (mixed $value, mixed $key, bool &$stop) use ($predicate, $method): bool {
        $ok = match ($method) {
            FilteringMethod::useKey => $predicate($key),
            FilteringMethod::useValue => $predicate($value),
            default => $predicate($value, $key),
        };
        if ($ok) {
            $stop = true;
        }
        return $ok;
    }, $array);
    if (count($array) === 0) {
        return null;
    }
    return reset($array);
}

/**
 * @template Index of array-key
 * @template Element
 * @param array<Index, Element> $array
 * @param callable|null $where
 * @param FilteringMethod $method
 * @return Element|null
 */
function array_last(array $array, callable $where = null, FilteringMethod $method = FilteringMethod::useValue)
{
    return array_first(array_reverse($array), $where, $method);
}

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

#[Pure]
function substring_from_index(string $string, int $index): string
{
    return substr($string, $index, strlen($string));
}

#[Pure]
function substring_to_index(string $string, int $index): string
{
    return substr($string, 0, $index);
}

/**
 * Returns a case and diacritic-insensitive string value.
 * @param string $string
 * @return string
 */
function canonical(string $string): string
{
    return string_with_options($string, CompareOptions::caseInsensitive | CompareOptions::diacriticInsensitive);
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
    $string = string_with_options($string, $options);
    $substring = string_with_options($substring, $options);
    if ($options & CompareOptions::caseInsensitive) {
        return stripos($string, $substring) !== false;
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
    if (($options != CompareOptions::none) && ($collator = Collator::create('root'))) {
        $collator->setAttribute(Collator::STRENGTH, Collator::PRIMARY);
        if (!($options & CompareOptions::diacriticInsensitive)) {
            $collator->setAttribute(Collator::STRENGTH, Collator::SECONDARY);
        }
        if (!($options & CompareOptions::caseInsensitive)) {
            $collator->setAttribute(Collator::STRENGTH, Collator::TERTIARY);
        }
        if (($options & CompareOptions::caseInsensitive) && ($options & CompareOptions::diacriticInsensitive) && !($options & CompareOptions::normalized)) {
            $collator->setAttribute(Collator::NORMALIZATION_MODE, Collator::ON);
        }
        return $collator->compare($string, $other);
    }
    $string = string_with_options($string, $options);
    $other = string_with_options($other, $options);
    if ($options & CompareOptions::caseInsensitive) {
        return max(min(strcasecmp($string, $other), ComparisonResult::orderedDescending->value), ComparisonResult::orderedAscending->value);
    }
    return max(min(strcmp($string, $other), ComparisonResult::orderedDescending->value), ComparisonResult::orderedAscending->value);
}

/**
 * Checks if two string are equal using the given options.
 * @param string $string The receiver string.
 * @param string $other The string with which to compare.
 * @param int $options The options for the comparison.
 * @return bool True if the strings are lexically equals.
 */
function string_is_equal(string $string, string $other, #[ExpectedValues(flagsFromClass: CompareOptions::class)] int $options = CompareOptions::none): bool
{
    return string_compare($string, $other, $options) === ComparisonResult::orderedSame->value;
}

/**
 * Returns a Boolean value indicating whether the initial characters of the string are the same as the characters in prefix.
 * @param string $string The receiver string.
 * @param string $prefix The string with which to compare.
 * @param int $options The options for the comparison.
 * @return bool true if the initial characters of the string are the same as the characters of prefix; otherwise, false.
 */
function string_has_prefix(string $string, string $prefix, #[ExpectedValues(flagsFromClass: CompareOptions::class)] int $options = CompareOptions::none): bool
{
    return string_is_equal(substring_to_index($string, strlen($prefix)), $prefix, $options);
}

function string_has_suffix(string $string, string $suffix, #[ExpectedValues(flagsFromClass: CompareOptions::class)] int $options = CompareOptions::none): bool
{
    return string_is_equal(substring_from_index($string, strlen($string) - strlen($suffix)), $suffix, $options);
}

function string_search(string $string, string $needle, SearchMethod $method = SearchMethod::matches, #[ExpectedValues(flagsFromClass: CompareOptions::class)] int $options = CompareOptions::none, array &$matches = null): int
{
    $string = string_with_options($string, $options);
    $needle = string_with_options($needle, $options);
    if (!($options & CompareOptions::quoted)) {
        $needle = preg_quote($needle);
    }
    $pattern = '/';
    $pattern .= match ($method) {
        SearchMethod::matches => "^$needle$",
        SearchMethod::beginsWith => "^$needle",
        SearchMethod::endsWith => "$needle$",
        SearchMethod::contains => ($options & CompareOptions::words) ? "(?:^|\W)$needle(?:$|\W)" : $needle
    };
    $pattern .= '/';
    if ($options & CompareOptions::caseInsensitive) {
        $pattern .= 'i';
    }
    $value = preg_match_all($pattern, $string, $matches);
    $matches = array_map(fn($match): string => trim($match), $matches[0]);
    return $value;
}

function string_matches(string $string, string $other, #[ExpectedValues(flagsFromClass: CompareOptions::class)] int $options = CompareOptions::none): bool
{
    return string_search($string, $other, SearchMethod::matches, $options) > 0;
}

function string_begins_with(string $string, string $other, #[ExpectedValues(flagsFromClass: CompareOptions::class)] int $options = CompareOptions::none): bool
{
    return string_search($string, $other, SearchMethod::beginsWith, $options) > 0;
}

function string_ends_with(string $string, string $other, #[ExpectedValues(flagsFromClass: CompareOptions::class)] int $options = CompareOptions::none): bool
{
    return string_search($string, $other, SearchMethod::endsWith, $options) > 0;
}

function string_contains(string $string, string $substring, #[ExpectedValues(flagsFromClass: CompareOptions::class)] int $options = CompareOptions::none): bool
{
    return string_search($string, $substring, SearchMethod::contains, $options) > 0;
}

function string_to_binary(string $string): string
{
    return implode(' ', array_map(fn(string $c): string => base_convert(unpack("H*", $c)[1], 16, 2), str_split($string)));
}

function string_from_binary(string $string): string
{
    return implode('', array_map(fn(string $c): string => pack('H*', dechex((int)bindec($c))), explode(' ', $string)));
}

/**
 * Returns a localized version of the string designated by the specified key and residing in the specified table.
 * @param string $string The key for a string in the specified table.
 * @param string $domain The name of the table containing the key-value pairs.
 * @param string $directory The directory containing the strings file.
 * @param string $comment The comment to place above the key-value pair in the strings file.
 * @return string The localized string.
 * @noinspection PhpUnusedParameterInspection
 */
function localized_string(string $string, string $domain = 'Localizable', string $directory = '', string $comment = ''): string
{
    $directoryUrl = URL::fileURL($directory);
    $fileManager = FileManager::default();
    if (!$fileManager->fileExists($directoryUrl->path, $isDirectory) || !$isDirectory) {
        $directoryUrl = $fileManager->documentRootDirectory->appendingPathComponent('Resources');
    }
    if ((!$fileManager->fileExists($directoryUrl->path, $isDirectory) || !$isDirectory) && ($bundle = Bundle::bundleForClass(FileManager::class))) {
        $directoryUrl = $bundle->bundleURL;
    }
    if (!string_is_equal($directoryUrl->lastPathComponent, 'Resources')) {
        $directoryUrl = $directoryUrl->appendingPathComponent('Resources');
    }
    bindtextdomain($domain, $directoryUrl->path);
    bind_textdomain_codeset($domain, 'UTF-8');
    textdomain($domain);
    return gettext($string);
}

function url_encode(string $url, string $endpoint, array $parameters = []): string
{
    if (!string_has_suffix($url, '/')) {
        $url .= '/';
    }
    $url .= $endpoint;
    if (!empty($parameters)) {
        $url .= "?" . http_build_query($parameters);
    }
    return $url;
}

function document_root_directory(): string
{
    $path = $_SERVER['DOCUMENT_ROOT'];
    if (is_running_from_cli()) {
        if (isset($_SERVER['PWD'])) {
            $path = $_SERVER['PWD'];
        }
        if (empty($path)) {
            $path = getcwd();
        }
    }
    return $path;
}

/**
 * Returns the path to either the user's home directory, depending on the platform.
 * @return string The path to the current home directory.
 */
function home_directory(): string
{
    if ($path = getenv('HOME')) {
        return rtrim($path, '/');
    } elseif (!empty($_SERVER['HOMEDRIVE']) && !empty($_SERVER['HOMEPATH'])) {
        return rtrim($_SERVER['HOMEDRIVE'] . $_SERVER['HOMEPATH'], '\\/');
    } else {
        throw new RuntimeException("failed to get current user directory");
    }
}

/**
 * A string containing the full name of the current user.
 * @return string
 */
function full_user_name(): string
{
    /** @noinspection SpellCheckingInspection */
    if (function_exists('posix_getpwuid')) {
        return posix_getpwuid(posix_geteuid())['name'] ?? get_current_user();
    }
    return get_current_user();
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
 * @throws Exception
 */
function is_hidden(string $filename): bool
{
    /** @psalm-suppress TypeDoesNotContainType */
    if (USE_UNSAFE_FUNCTIONS) : // @phpstan-ignore-line
        if (target_os_win()) {
            /** @psalm-suppress ForbiddenCode */
            $attributes = trim(unsafe_value(fn(): string|bool|null => shell_exec("FOR %A IN (" . "\"" . $filename . "\"" . ") DO @ECHO %~aA")));
            return $attributes[3] === 'h' || $attributes[4] === 's';
        }
    endif;
    return string_has_prefix($filename, '.');
}

function is_serialized(mixed $value, bool $strict = true): bool
{
    // If it isn't a string, it isn't serialized.
    if (!is_string($value)) {
        return false;
    }
    $value = trim($value);
    if ('N;' === $value) {
        return true;
    }
    if (strlen($value) < 4) {
        return false;
    }
    if (':' !== $value[1]) {
        return false;
    }
    if ($strict) {
        $last = substr($value, -1);
        if (';' !== $last && '}' !== $last) {
            return false;
        }
    } else {
        $semicolon = strpos($value, ';');
        $brace = strpos($value, '}');
        // Either ; or } must exist.
        if (false === $semicolon && false === $brace) {
            return false;
        }
        // But neither must be in the first X characters.
        if (false !== $semicolon && $semicolon < 3) {
            return false;
        }
        if (false !== $brace && $brace < 4) {
            return false;
        }
    }
    $token = $value[0];
    switch ($token) {
        case 's':
            if ($strict) {
                if ('"' !== substr($value, -2, 1)) {
                    return false;
                }
            } elseif (!str_contains($value, '"')) {
                return false;
            }
            break;
        // Or else fall through.
        case 'a':
        case 'O':
            return (bool)preg_match("/^$token:\d+:/s", $value);
        case 'b':
        case 'i':
        case 'd':
            $end = $strict ? '$' : '';
            return (bool)preg_match("/^$token:[\d.E+-]+;$end/", $value);
    }
    return false;
}

function equivalent(mixed $a, mixed $b): bool
{
    return $a instanceof Equatable ? $a->isEqual($b) : $a === $b;
}
