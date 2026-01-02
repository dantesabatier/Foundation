<?php

namespace Sabatier\Foundation;

use BackedEnum;
use Closure;
use Exception;
use JetBrains\PhpStorm\Deprecated;
use JetBrains\PhpStorm\Pure;
use MessageFormatter;
use NumberFormatter;
use Stringable;

if (!defined("TARGET_OS_WINDOWS")) {
    define("TARGET_OS_WINDOWS", str_contains(PHP_OS, "WIN"));
}

if (!defined("RUNNING_FROM_CLI")) {
    define("RUNNING_FROM_CLI", ((PHP_SAPI === "cli") || (stristr(PHP_SAPI, "cgi") && getenv("TERM"))));
}

if (!defined("HAS_ESCAPE_SEQUENCES")) {
    define("HAS_ESCAPE_SEQUENCES", RUNNING_FROM_CLI && (function_exists("posix_isatty") ? posix_isatty(STDOUT) : (getenv("ANSICON") !== false || getenv("ConEmuANSI") === "ON")));
}

/**
 * Logs a debug message to the standard output.
 *
 * @param string $string The debug message to be logged.
 */
function debuglog(string $string): void
{
    print "$string\n";
}

/**
 * Logs a message to the standard output and triggers a deprecation notice.
 *
 * @param string $string The message to be logged.
 */
#[Deprecated("since Foundation 0.1, use debuglog() instead", "debuglog(%parametersList%)")]
function cli_log(string $string): void
{
    trigger_error(sprintf("%s() is deprecated, use debuglog() instead", __FUNCTION__), E_USER_DEPRECATED);
    debuglog($string);
}

/**
 * Formats the given string with ANSI escape sequences for applying text attributes, foreground color, and background color.
 *
 * @param string $string The text to be formatted with ANSI escape sequences.
 * @param EscapeSequenceTextAttribute $textAttribute The text attribute to apply (e.g., bold, underline). Defaults to normal.
 * @param EscapeSequenceColor $foregroundColor The foreground color to apply. Defaults to white.
 * @param EscapeSequenceColor $backgroundColor The background color to apply. Defaults to none.
 * @return string The formatted string with applied ANSI escape sequences.
 */
function escape_sequence(string $string, EscapeSequenceTextAttribute $textAttribute = EscapeSequenceTextAttribute::normal, EscapeSequenceColor $foregroundColor = EscapeSequenceColor::white, EscapeSequenceColor $backgroundColor = EscapeSequenceColor::none): string
{
    return sprintf("\e[%s;%s;%sm%s\e[0m", $textAttribute->value, $foregroundColor->value, $backgroundColor->value + EscapeSequenceBackgroundColorAddition, $string);
}

/**
 * Determines and returns the type of the given value in a human-readable format.
 *
 * @param mixed $value The value for which to determine the type.
 * @return string A string representing the type of the given value.
 */
function typeof(mixed $value): string
{
    return get_debug_type($value);
}

/**
 * Converts a given value to a human-readable string representation.
 *
 * @param mixed $value The value to be converted can be of any type (e.g., scalar, array, object, null, etc.).
 * @return string A string representation of the provided value in a human-readable format.
 */
function human_readable_value(mixed $value): string
{
    if (is_string($value)) {
        return $value;
    }
    if (is_null($value)) {
        return "null";
    }
    if (is_bool($value)) {
        return $value ? "true" : "false";
    }
    if (is_array($value)) {
        return "[" . implode(", ", array_map(fn(mixed $index, mixed $element): string => sprintf("%s: %s", $index, human_readable_value($element)), array_keys($value), array_values($value))) . "]";
    }
    if (is_scalar($value)) {
        return (string)$value;
    }
    if (is_object($value)) {
        if ($value instanceof Stringable) {
            return (string)$value;
        }
        if ($value instanceof BackedEnum) {
            return sprintf("%s::%s", $value::class, $value->name);
        }
    }
    return typeof($value);
}

/**
 * Converts a time interval given in seconds into a human-readable string format
 * comprising months, days, hours, minutes, seconds, and milliseconds.
 *
 * @param float $seconds The time interval in seconds to be converted.
 * @param string $locale The locale to use for number formatting. Defaults to "en_US".
 * @return string A human-readable string describing the given time interval.
 * @throws Exception
 */
#[Pure]
function human_readable_time(float $seconds, string $locale = "en_US"): string
{
    $units = ["year" => 31_536_000, "month" => 2_592_000, "day" => 86400, "hour" => 3600, "minute" => 60, "second" => 1];
    $parts = [];
    $remainder = $seconds;
    foreach ($units as $unit => $value) {
        if ($remainder >= $value) {
            $amount = (int)floor($remainder / $value);
            $remainder -= $amount * $value;
            $parts[] = new MessageFormatter($locale, "{n, plural, =1 {1 $unit} other {# {$unit}s}}")->format(["n" => $amount]);
        }
    }
    if ($remainder > 0 || $parts === []) {
        $ms = round($remainder * 1000);
        $parts[] = new MessageFormatter($locale, "{n, plural, =1 {1 milisegundo} other {# milisegundos}}")->format(["n" => $ms]);
    }
    return implode(", ", $parts);
}

/**
 * Converts a size value given in bytes into a human-readable string format
 * comprising bytes, kilobytes, megabytes, or gigabytes.
 *
 * @param float $bytes The size value in bytes to be converted.
 * @param string $locale The locale to use for number formatting. Defaults to "en_US".
 * @return string A human-readable string describing the given size value.
 */
function human_readable_bytes(float $bytes, string $locale = "en_US"): string
{
    $units = ["B", "KB", "MB", "GB", "TB", "PB"];
    $i = $bytes > 0 ? (int)floor(log($bytes, 1024)) : 0;
    $i = min($i, count($units) - 1);
    $value = $bytes / pow(1024, $i);
    $formatter = new NumberFormatter($locale, NumberFormatter::DECIMAL);
    $formatter->setAttribute(NumberFormatter::MAX_FRACTION_DIGITS, $i === 0 ? 0 : 2);
    $formatter->setAttribute(NumberFormatter::MIN_FRACTION_DIGITS, $i === 0 ? 0 : 2);
    return $formatter->format($value) . " " . $units[$i];
}

#[Deprecated("since Foundation 0.1, use pluralize() instead", "pluralize(%parametersList%)")]
function human_readable_plural(string $string, int|float $number): string
{
    return sprintf("%s%s", $string, $number === 0 || $number > 1 ? "s" : "");
}

function pluralize(string $entity, int|float $count, string $locale = "en_US"): string
{
    return MessageFormatter::formatMessage($locale, "{count, plural, one {{$entity}}  other {{$entity}s}}", ["count" => $count]);
}

/**
 * @param string $message The string to print. The default is an empty string.
 * @param string $file The file name to print with the message. The default is the file where fatal_error() is called.
 * @param int $line The line number to print along with the message. The default is the file where fatal_error() is called.
 */
function fatal_error(string $message = "", string $file = "", int $line = 0): never
{
    if (!$file || !$line) {
        $backtrace = debug_backtrace()[0] ?? [];
        if (isset($backtrace["file"])) {
            $file = $backtrace["file"];
        }
        if (isset($backtrace["line"])) {
            $line = $backtrace["line"];
        }
    }
    throw new InternalInconsistencyException($message, 0, 0, $file, $line);
}

/**
 * Triggers a fatal error indicating that a function or method is not yet implemented.
 *
 * @param object|string $objectOrClass The object instance or class name where the method resides.
 * @param string $fn The name of the function or method that is not implemented.
 * @return never This function does not return a value, as it terminates execution with a fatal error.
 */
function unimplemented(object|string $objectOrClass, string $fn): never
{
    fatal_error(sprintf("%s %s() is not yet implemented", is_object($objectOrClass) ? $objectOrClass::class : $objectOrClass, $fn));
}

/**
 * Throws a fatal error indicating that a specific method is not supported for a given object or class.
 *
 * @param object|string $objectOrClass The object or class for which the method is not supported.
 * @param string $fn The name of the unsupported method.
 * @return never The function does not return a value as it triggers a fatal error.
 */
function unsupported(object|string $objectOrClass, string $fn): never
{
    fatal_error(sprintf("%s %s() is not supported", is_object($objectOrClass) ? $objectOrClass::class : $objectOrClass, $fn));
}

/**
 * Triggers a fatal error indicating that a specific method in the given object or class requires a concrete subclass implementation.
 *
 * @param object|string $objectOrClass The object instance or class name where the missing concrete implementation was detected.
 * @param string $fn The name of the method that requires a subclass implementation.
 * @return never This method does not return a value as it triggers a fatal error.
 */
function request_concrete_implementation(object|string $objectOrClass, string $fn): never
{
    fatal_error(sprintf("%s %s() requires a subclass implementation", is_object($objectOrClass) ? $objectOrClass::class : $objectOrClass, $fn));
}

/**
 * Triggers a fatal error when an attempt is made to perform an invalid mutation on an immutable object.
 *
 * @return never This method does not return as it results in a fatal error.
 */
function invalid_mutation(): never
{
    fatal_error("attempting to mutate an immutable object");
}

/**
 * @template Result
 * Executes a given closure with a custom error handler to ensure more controlled error handling.
 *
 * @param Closure(): Result $block The closure to be executed within the custom error handler context.
 * @return Result The value returned by the executed closure.
 */
function unsafe_value(Closure $block)
{
    set_error_handler(fn(int $severity, string $message, string $file, int $line): never => fatal_error($message, $file, $line));
    $value = $block();
    restore_error_handler();
    return $value;
}

/**
 * Extracts the class name from the fully qualified class name, optionally providing its namespace.
 *
 * @param string $class The fully qualified class name to process.
 * @param string|null &$namespace A variable passed by reference to hold the extracted namespace, if applicable.
 * @return string The class name extracted from the fully qualified class name.
 */
function class_name(string $class, ?string &$namespace = null): string
{
    if (str_contains($class, "\\")) {
        $components = new ArrayClass(explode("\\", $class));
        /** @var string $class */
        $class = $components->popLast();
        if (func_num_args() > 1) {
            $namespace = $components->join("\\");
        }
    }
    return $class;
}

/**
 * Determines the name of the class from which the current method was called, navigating through the backtrace to identify the first differing object.
 *
 * @return string|null The fully qualified name of the calling class, or null if not applicable.
 */
function get_calling_class(): ?string
{
    $backtrace = debug_backtrace();
    $object = $backtrace[1]["object"] ?? null;
    $backtraceCount = count($backtrace);
    for ($i = 1; $i < $backtraceCount; $i++) {
        if (isset($backtrace[$i])) {
            $current = $backtrace[$i]["object"] ?? null;
            if ($object !== $current) {
                if (is_object($current)) {
                    return $current::class;
                }
                return $current;
            }
        }
    }
    return null;
}
