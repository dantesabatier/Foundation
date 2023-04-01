<?php

namespace Sabatier\Foundation;

use BackedEnum;
use Closure;
use ErrorException;
use Exception;
use InvalidArgumentException;
use JetBrains\PhpStorm\Deprecated;
use JetBrains\PhpStorm\Pure;
use Stringable;

if (!defined("TARGET_OS_WINDOWS")) {
    define("TARGET_OS_WINDOWS", str_contains(PHP_OS, "WIN"));
}

if (!defined("RUNNING_FROM_CLI")) {
    define("RUNNING_FROM_CLI", ((PHP_SAPI === "cli") || (stristr(PHP_SAPI, "cgi") && getenv("TERM"))));
}

if (!defined("HAS_ESCAPE_SEQUENCES")) {
    define("HAS_ESCAPE_SEQUENCES", function_exists("posix_isatty") ? posix_isatty(STDOUT) : (getenv("ANSICON") !== false || getenv("ConEmuANSI") === "ON"));
}

function debuglog(string $string): void
{
    print $string . PHP_EOL;
}

#[Deprecated("since Foundation 0.1, use debuglog() instead", "debuglog(%parametersList%)")]
function cli_log(string $string): void
{
    trigger_error(sprintf("%s() is deprecated, use debuglog() instead", __FUNCTION__), E_USER_DEPRECATED);
    debuglog($string);
}

function escape_sequence(string $string, EscapeSequenceTextAttribute $textAttribute = EscapeSequenceTextAttribute::normal, EscapeSequenceColor $foregroundColor = EscapeSequenceColor::white, EscapeSequenceColor $backgroundColor = EscapeSequenceColor::black): string
{
    return sprintf("\e[%s;%s;%sm%s\e[0m", $textAttribute->value, $foregroundColor->value, $backgroundColor->value + EscapeSequenceBackgroundColorAddition, $string);
}

function typeof(mixed $value): string
{
    return get_debug_type($value);
}

function human_readable_value(mixed $value): string
{
    if (is_string($value)) {
        return $value;
    } elseif (is_null($value)) {
        return "null";
    } elseif (is_bool($value)) {
        return $value ? "true" : "false";
    } elseif (is_array($value)) {
        return "[" . implode(", ", array_map(fn(mixed $index, mixed $element): string => sprintf("%s: %s", $index, human_readable_value($element)), array_keys($value), array_values($value))) . "]";
    } elseif (is_scalar($value)) {
        return (string)$value;
    } elseif (is_object($value)) {
        if ($value instanceof Stringable) {
            return (string)$value;
        } elseif ($value instanceof BackedEnum) {
            return sprintf("%s::%s", $value::class, $value->name);
        }
    }
    return typeof($value);
}

#[Pure]
function human_readable_time(float $interval): string
{
    $s = (int)$interval % 60;
    $m = (int)floor(((int)$interval % 3600) / 60);
    $h = (int)floor(((int)$interval % 86400) / 3600);
    $d = (int)floor(((int)$interval % 2_592_000) / 86400);
    $M = (int)floor((int)$interval / 2_592_000);
    $string = "";
    if ($M) {
        $string .= sprintf("%d month%s", $M, ($M > 1) ? "s" : "");
        $string .= " ";
        $interval -= $M * 2_592_000;
    }
    if ($d) {
        $string .= sprintf("%d day%s", $d, ($d > 1) ? "s" : "");
        $string .= " ";
        $interval -= $d * 86400;
    }
    if ($h) {
        $string .= sprintf("%d hour%s", $h, ($h > 1) ? "s" : "");
        $string .= " ";
        $interval -= $h * 3600;
    }
    if ($m) {
        $string .= sprintf("%d minute%s", $m, ($m > 1) ? "s" : "");
        $string .= " ";
        $interval -= $m * 60;
    }
    if ($s) {
        $string .= sprintf("%d second%s", $s, ($s > 1) ? "s" : "");
        $string .= " ";
        $interval -= $s;
    }
    return $string . sprintf("%.f seconds", $interval);
}

/**
 * @param string $message The string to print. The default is an empty string.
 * @param string $file The file name to print with message. The default is the file where fatal_error() is called.
 * @param int $line The line number to print along with message. The default is the file where fatal_error() is called.
 * @throws Exception
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
    throw new ErrorException($message, 0, 0, $file, $line);
}

/**
 * @throws Exception
 */
function unimplemented(object|string $objectOrClass, string $fn): never
{
    throw new InvalidArgumentException(sprintf("%s %s() is not yet implemented", is_object($objectOrClass) ? $objectOrClass::class : $objectOrClass, $fn));
}

/**
 * @throws Exception
 */
function unsupported(object|string $objectOrClass, string $fn): never
{
    throw new InvalidArgumentException(sprintf("%s %s() is not supported", is_object($objectOrClass) ? $objectOrClass::class : $objectOrClass, $fn));
}

function request_concrete_implementation(object|string $objectOrClass, string $fn): never
{
    throw new InvalidArgumentException(sprintf("%s %s() requires a subclass implementation", is_object($objectOrClass) ? $objectOrClass::class : $objectOrClass, $fn));
}

function invalid_mutation(): never
{
    throw new InternalInconsistencyException("attempting to mutate an immutable object");
}

/**
 * @template Result
 * @param Closure(): Result $block
 * @return Result
 * @throws Exception
 */
function unsafe_value(Closure $block): mixed
{
    set_error_handler(/** @throws Exception */ fn(int $severity, string $message, string $file, int $line): bool => throw new ErrorException($message, 0, $severity, $file, $line)); // @phpstan-ignore-line
    $value = $block();
    restore_error_handler();
    return $value;
}

/**
 * Return the name of the given class
 * @param class-string $class
 */
function class_name(string $class): string
{
    if (str_contains($class, "\\")) {
        $class = array_last(explode("\\", $class));
        assert($class !== null);
    }
    return $class;
}

/**
 * Returns the calling class of the current method (if any).
 * @return class-string|null
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
                    $current = $current::class;
                }
                return $current;
            }
        }
    }
    return null;
}
