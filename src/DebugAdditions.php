<?php

namespace Sabatier\Foundation;

use BackedEnum;
use Closure;
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
    define("HAS_ESCAPE_SEQUENCES", RUNNING_FROM_CLI && (function_exists("posix_isatty") ? posix_isatty(STDOUT) : (getenv("ANSICON") !== false || getenv("ConEmuANSI") === "ON")));
}

function debuglog(string $string): void
{
    print "$string\n";
}

#[Deprecated("since Foundation 0.1, use debuglog() instead", "debuglog(%parametersList%)")]
function cli_log(string $string): void
{
    trigger_error(sprintf("%s() is deprecated, use debuglog() instead", __FUNCTION__), E_USER_DEPRECATED);
    debuglog($string);
}

function escape_sequence(string $string, EscapeSequenceTextAttribute $textAttribute = EscapeSequenceTextAttribute::normal, EscapeSequenceColor $foregroundColor = EscapeSequenceColor::white, EscapeSequenceColor $backgroundColor = EscapeSequenceColor::none): string
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
    return $string . sprintf("%.f milliseconds", $interval);
}

function human_readable_bytes(float $value): string
{
    if ($value >= 1 << 30) {
        return number_format($value / (1 << 30), 2) . " GB";
    }
    if ($value >= 1 << 20) {
        return number_format($value / (1 << 20), 2) . " MB";
    }
    if ($value >= 1 << 10) {
        return number_format($value / (1 << 10), 2) . " KB";
    }
    return number_format($value) . " bytes";
}

function human_readable_plural(string $string, int|float $number): string
{
    return sprintf("%s%s", $string, $number === 0 || $number > 1 ? "s" : "");
}

/**
 * @param string $message The string to print. The default is an empty string.
 * @param string $file The file name to print with message. The default is the file where fatal_error() is called.
 * @param int $line The line number to print along with message. The default is the file where fatal_error() is called.
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

function unimplemented(object|string $objectOrClass, string $fn): never
{
    fatal_error(sprintf("%s %s() is not yet implemented", is_object($objectOrClass) ? $objectOrClass::class : $objectOrClass, $fn));
}

function unsupported(object|string $objectOrClass, string $fn): never
{
    fatal_error(sprintf("%s %s() is not supported", is_object($objectOrClass) ? $objectOrClass::class : $objectOrClass, $fn));
}

function request_concrete_implementation(object|string $objectOrClass, string $fn): never
{
    fatal_error(sprintf("%s %s() requires a subclass implementation", is_object($objectOrClass) ? $objectOrClass::class : $objectOrClass, $fn));
}

function invalid_mutation(): never
{
    fatal_error("attempting to mutate an immutable object");
}

/**
 * @template Result
 * @param Closure(): Result $block
 * @return Result
 */
function unsafe_value(Closure $block)
{
    set_error_handler(fn(int $severity, string $message, string $file, int $line): bool => fatal_error($message, $file, $line));
    $value = $block();
    restore_error_handler();
    return $value;
}

/**
 * Return the name (and namespace) of the given class
 * @param class-string $class
 * @param string|null $namespace
 * @return string
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
                    return $current::class;
                }
                return $current;
            }
        }
    }
    return null;
}
