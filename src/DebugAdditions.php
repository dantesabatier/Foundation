<?php

declare(strict_types=1);

namespace Sabatier\Foundation;

use BackedEnum;
use Closure;
use Exception;
use JetBrains\PhpStorm\Deprecated;
use MessageFormatter;
use NumberFormatter;
use Stringable;

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
    $backgroundCode = $backgroundColor === EscapeSequenceColor::none ? "" : ";" . ($backgroundColor->value + EscapeSequenceBackgroundColorAddition);
    return sprintf("\e[%s;%s%sm%s\e[0m", $textAttribute->value, $foregroundColor->value, $backgroundCode, $string);
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
 * Converts any value to a human-readable string representation.
 *
 * This function recursively converts values of any type into a string format
 * suitable for debugging and logging purposes. It handles primitives, arrays,
 * objects, and special types with appropriate formatting.
 *
 * @param mixed $value The value to convert to a readable string
 * @param int $depth Current recursion depth (used internally for nested structures)
 * @param int $maxDepth Maximum recursion depth to prevent stack overflow (default: 10)
 *
 * @return string A human-readable string representation of the value
 *
 * Type-specific behavior:
 * - null: Returns "null"
 * - bool: Returns "true" or "false"
 * - string: Returns the string as-is
 * - int/float: Converts to string representation
 * - array: Formats as "[key1: value1, key2: value2, ...]" (recursive)
 * - Stringable objects: Uses the __toString() method
 * - BackedEnum: Returns "ClassName::CASE_NAME"
 * - Other objects: Returns the class name
 * - Resources/other: Returns the debug type name
 *
 * <code>
 *  human_readable_value(null); // "null"
 *  human_readable_value(true); // "true"
 *  human_readable_value([1, 2, 3]); // "[0: 1, 1: 2, 2: 3]"
 *  human_readable_value(["a" => "b"]); // "[a: b]"
 *  human_readable_value(Status::Active); // "Status::Active"
 * </code>
 */
function human_readable_value(mixed $value, int $depth = 0, int $maxDepth = 10): string
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
    if (is_numeric($value)) {
        return (string)$value;
    }
    if (is_array($value)) {
        return human_readable_array($value, $depth, $maxDepth);
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
 * @internal
 * @param array<array-key, mixed> $value
 * @param int $depth
 * @param int $maxDepth
 * @return string
 */
function human_readable_array(array $value, int $depth, int $maxDepth): string
{
    if ($value === []) {
        return "[]";
    }
    if ($depth >= $maxDepth) {
        return "[max depth]";
    }
    return "[" . implode(", ", array_map(fn(mixed $key, mixed $element): string => sprintf("%s: %s", is_string($key) ? $key : (string)$key, human_readable_value($element, $depth + 1, $maxDepth)), array_keys($value), array_values($value))) . "]";
}

/**
 * Converts a duration in seconds into a human-readable, comma-separated string of time units.
 *
 * This function calculates the duration using a greedy approach, breaking it down from
 * years to seconds. Any remaining fractional seconds are converted to milliseconds.
 *
 * Time definitions used:
 * - Year: 365 days (31,536,000 seconds)
 * - Month: 30 days (2,592,000 seconds)
 *
 * @param float $seconds The time duration in seconds.
 * @param string $locale The locale used for number formatting within the plural rules (default: "en_US").
 *
 * @return string A comma-separated string of time components (e.g., "1 hour, 30 minutes").
 * @throws Exception
 */
function human_readable_time(float $seconds, string $locale = "en_US"): string
{
    $units = ["year" => 31_536_000, "month" => 2_592_000, "day" => 86400, "hour" => 3600, "minute" => 60, "second" => 1];
    $parts = [];
    $remainder = $seconds;
    foreach ($units as $unit => $secondsPerUnit) {
        if ($remainder >= $secondsPerUnit) {
            $amount = (int)floor($remainder / $secondsPerUnit);
            $remainder -= $amount * $secondsPerUnit;
            $parts[] = new MessageFormatter($locale, "{n, plural, =1 {1 $unit} other {# {$unit}s}}")->format(["n" => $amount]);
        }
    }
    if ($remainder > 0 || $parts === []) {
        $milliseconds = round($remainder * 1000);
        $parts[] = new MessageFormatter($locale, "{n, plural, =1 {1 millisecond} other {# milliseconds}}")->format(["n" => $milliseconds]);
    }
    return implode(", ", $parts);
}

/**
 * Converts a size in bytes to a human-readable string.
 *
 * This function calculates the appropriate unit (B, KB, MB, etc.) using a binary base (1024).
 * It uses PHP's NumberFormatter to ensure the numeric value respects the specific
 * locale settings (e.g., decimal separators).
 *
 * Precision logic:
 * - Bytes (B): Formatted with 0 decimal places.
 * - Larger units (KB, MB, etc.): Formatted with fixed 2 decimal places.
 *
 * @param float $bytes The raw size in bytes to convert.
 * @param string $locale The locale string used for number formatting (default: "en_US").
 *
 * @return string The formatted string including the unit (e.g., "1.50 MB").
 */
function human_readable_bytes(float $bytes, string $locale = "en_US"): string
{
    $units = ["B", "KB", "MB", "GB", "TB", "PB"];
    $unitIndex = $bytes > 0 ? (int)floor(log($bytes, 1024)) : 0;
    $unitIndex = max(min($unitIndex, count($units) - 1), 0);
    $value = $bytes / 1024 ** $unitIndex;
    $formatter = new NumberFormatter($locale, NumberFormatter::DECIMAL);
    $formatter->setAttribute(NumberFormatter::MAX_FRACTION_DIGITS, $unitIndex === 0 ? 0 : 2);
    $formatter->setAttribute(NumberFormatter::MIN_FRACTION_DIGITS, $unitIndex === 0 ? 0 : 2);
    return $formatter->format($value) . " " . $units[$unitIndex];
}

/**
 * Appends "s" to an entity when the number is zero or greater than one.
 *
 * @param string $string The entity to pluralize.
 * @param int|float $number The number that determines whether to append the suffix.
 * @return string The singular or plural entity.
 */
#[Deprecated("since Foundation 0.1, use pluralize() instead", "pluralize(%parametersList%)")]
function human_readable_plural(string $string, int|float $number): string
{
    return sprintf("%s%s", $string, $number === 0 || $number > 1 ? "s" : "");
}

/**
 * Pluralizes a given entity string based on the provided count.
 *
 * This function uses MessageFormatter to apply pluralization rules.
 * Note: The current implementation strictly appends an 's' for the plural form
 * and does not handle irregular plurals (e.g., "child" -> "children").
 *
 * @param string $entity The singular name of the entity (e.g., "apple").
 * @param int|float $count The quantity used to determine whether to pluralize.
 * @param string $locale The locale string to use for formatting rules (default: "en_US").
 *
 * @return string The formatted string (singular or plural).
 */
function pluralize(string $entity, int|float $count, string $locale = "en_US"): string
{
    return MessageFormatter::formatMessage($locale, "{count, plural, one {{$entity}}  other {{$entity}s}}", ["count" => $count]);
}

/**
 * Throws an internal inconsistency exception with fatal-error severity.
 *
 * @param string $message The string to print. The default is an empty string.
 * @param string $file The file name to print with the message. The default is the file where fatal_error() is called.
 * @param int $line The line number to print along with the message. The default is the file where fatal_error() is called.
 */
function fatal_error(string $message = "", string $file = "", int $line = 0): never
{
    if (!$file || !$line) {
        $trace = debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS, 2)[1] ?? [];
        $file = $file ?: ($trace["file"] ?? "unknown");
        $line = $line ?: ($trace["line"] ?? 0);
    }
    throw new InternalInconsistencyException($message, 0, E_ERROR, $file, $line);
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
    fatal_error(sprintf("%s %s is not yet implemented", is_object($objectOrClass) ? $objectOrClass::class : $objectOrClass, $fn));
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
    fatal_error(sprintf("%s %s is not supported", is_object($objectOrClass) ? $objectOrClass::class : $objectOrClass, $fn));
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
    fatal_error(sprintf("%s %s requires a subclass implementation", is_object($objectOrClass) ? $objectOrClass::class : $objectOrClass, $fn));
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
 * Executes a closure while converting every PHP diagnostic handled by set_error_handler() into an exception.
 *
 * @param Closure(): Result $block The closure to be executed within the custom error handler context.
 * @return Result The value returned by the executed closure.
 * @throws InternalInconsistencyException
 */
function unsafe_value(Closure $block)
{
    set_error_handler(fn(int $severity, string $message, string $file, int $line): never => fatal_error($message, $file, $line));
    try {
        return $block();
    } finally {
        restore_error_handler();
    }
}

/**
 * Extracts the class name from the fully qualified class name, optionally providing its namespace.
 *
 * @param class-string $class The fully qualified class name to process.
 * @param string|null &$namespace A variable passed by reference to hold the extracted namespace, if applicable.
 * @return string The class name extracted from the fully qualified class name.
 */
function class_name(string $class, ?string &$namespace = null): string
{
    if (str_contains($class, "\\")) {
        $separatorIndex = strrpos($class, "\\");
        assert($separatorIndex !== false);
        if (func_num_args() > 1) {
            $namespace = substr($class, 0, $separatorIndex);
        }
        return substr($class, $separatorIndex + 1);
    }
    return $class;
}

/**
 * Determines the name of the class from which the current method was called, navigating through the backtrace to identify the first differing object.
 *
 * @return class-string|null The fully qualified name of the calling class, or null if not applicable.
 */
function get_calling_class(): ?string
{
    $backtrace = debug_backtrace();
    $object = $backtrace[1]["object"] ?? null;
    $backtraceCount = count($backtrace);
    for ($index = 1; $index < $backtraceCount; $index++) {
        if (isset($backtrace[$index])) {
            $current = $backtrace[$index]["object"] ?? null;
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
