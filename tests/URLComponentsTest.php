<?php

declare(strict_types=1);

/**
 * Standalone tests for src/URLComponents.php.
 *
 * Run with: php tests/URLComponentsTest.php
 * Exits with a non-zero status code if any check fails.
 *
 * The query-item parsing section is a regression guard: a query pair must split
 * on the first "=" only, a valueless parameter must not raise a warning, an empty
 * value must be preserved as distinct from an absent one, and a duplicated name
 * must yield one item per occurrence.
 */

namespace Sabatier\Foundation\Tests;

use Sabatier\Foundation\ArrayClass;
use Sabatier\Foundation\URLComponents;
use Sabatier\Foundation\URLQueryItem;

require __DIR__ . "/../vendor/autoload.php";

final class URLComponentsTestRunner
{
    public static int $passed = 0;
    /** @var list<string> */
    public static array $failures = [];
    private static string $section = "";

    public static function section(string $name): void
    {
        self::$section = $name;
    }

    public static function check(bool $condition, string $message): void
    {
        if ($condition) {
            self::$passed++;
            return;
        }
        $failure = self::$section === "" ? $message : self::$section . ": " . $message;
        self::$failures[] = $failure;
        fwrite(STDERR, "FAIL $failure" . PHP_EOL);
    }

    public static function finish(): never
    {
        $failed = count(self::$failures);
        printf("%d passed, %d failed%s", self::$passed, $failed, PHP_EOL);
        exit($failed > 0 ? 1 : 0);
    }
}

/**
 * Turns the query items of the given URL into a plain [name => value, ...] list of pairs,
 * preserving order and duplicates.
 *
 * @return list<array{string, string|null}>
 */
function query_pairs(string $url): array
{
    $items = new URLComponents($url)->queryItems;
    if ($items === null) {
        return [];
    }
    return $items->map(fn(URLQueryItem $item): array => [$item->name, $item->value])->array;
}

/** Fails the process on any PHP warning/notice, so a resurfaced "Undefined array key" is caught. */
set_error_handler(function (int $severity, string $message, string $file, int $line): bool {
    throw new \ErrorException($message, 0, $severity, $file, $line);
});

$check = URLComponentsTestRunner::check(...);
$section = URLComponentsTestRunner::section(...);

// ---------------------------------------------------------------------------
$section("constructor / parse_url");
// ---------------------------------------------------------------------------

$components = new URLComponents("https://user:secret@example.com:8443/a/b?x=1#frag");
$check($components->scheme === "https", "scheme parsed");
$check($components->user === "user", "user parsed");
$check($components->password === "secret", "password parsed (pass mapped to password)");
$check($components->host === "example.com", "host parsed");
$check($components->port === 8443, "port parsed as int");
$check($components->path === "/a/b", "path parsed");
$check($components->query === "x=1", "query parsed");
$check($components->fragment === "frag", "fragment parsed");

$empty = new URLComponents();
$check($empty->scheme === null, "empty components have null scheme");
$check($empty->queryItems === null, "no query means null queryItems");
$check($empty->string === null, "empty components produce null string");

// ---------------------------------------------------------------------------
$section("queryItems (get)");
// ---------------------------------------------------------------------------

$check(query_pairs("https://x.test/p?a=1&b=2") === [["a", "1"], ["b", "2"]], "two simple pairs");
$check(query_pairs("https://x.test/p?a=1&a=2") === [["a", "1"], ["a", "2"]], "duplicate name yields one item per occurrence, in order");
$check(query_pairs("https://x.test/p?flag") === [["flag", null]], "valueless parameter has null value, no warning");
$check(query_pairs("https://x.test/p?a=") === [["a", ""]], "empty value is preserved and distinct from absent");
$check(query_pairs("https://x.test/p?token=abc&admin") === [["token", "abc"], ["admin", null]], "mixed valued and valueless params");
$check(query_pairs("https://x.test/p?sig=a=b=c") === [["sig", "a=b=c"]], "split on first equals only; value may contain equals");

// Percent-decoding and HTML-escaping of the value.
$check(query_pairs("https://x.test/p?n=a%20b") === [["n", "a b"]], "percent-encoded space decoded in value");
$decoded = query_pairs("https://x.test/p?q=%3Cscript%3E");
$check($decoded[0][1] === "&lt;script&gt;", "value is HTML-escaped after decoding");

// ---------------------------------------------------------------------------
$section("queryItems (set)");
// ---------------------------------------------------------------------------

$roundTrip = new URLComponents();
$roundTrip->queryItems = new ArrayClass([new URLQueryItem("a", "1"), new URLQueryItem("b", "two words")]);
$check($roundTrip->query !== null && str_contains($roundTrip->query, "a=1"), "set queryItems builds query string");
$check($roundTrip->query !== null && str_contains($roundTrip->query, "b="), "set queryItems includes second key");
$check($roundTrip->query !== null && str_contains($roundTrip->query, "b=two+words"), "set queryItems preserves the parameter name, not a numeric index");
$check($roundTrip->query !== null && !str_contains($roundTrip->query, "0="), "set queryItems does not emit numeric index keys");

// Full round-trip: parsing a query and re-emitting it keeps names and values.
$source = new URLComponents("https://x.test/p?a=1&b=2");
$rebuilt = new URLComponents();
$rebuilt->queryItems = $source->queryItems;
$check($rebuilt->query === "a=1&b=2", "get then set round-trips the query string");

// ---------------------------------------------------------------------------
$section("string (reassembly)");
// ---------------------------------------------------------------------------

$reassembled = new URLComponents("https://example.com/path?x=1#frag");
$check($reassembled->string !== null && str_starts_with($reassembled->string, "https://example.com"), "string keeps scheme and host");
$check($reassembled->string !== null && str_contains($reassembled->string, "?x=1"), "string keeps query");
$check($reassembled->string !== null && str_contains($reassembled->string, "#frag"), "string keeps fragment");

// ---------------------------------------------------------------------------
$section("string (regressions)");
// ---------------------------------------------------------------------------

// A trailing path slash used to be lost in reassembly, which broke directory URLs.
$check(new URLComponents("https://example.com/a/b/")->string === "https://example.com/a/b/", "trailing path slash is preserved");
$check(new URLComponents("https://example.com/")->string === "https://example.com/", "root path does not become a double slash");

// An already percent-encoded password used to be encoded a second time.
$check(new URLComponents("https://u:p%40ss@example.com/x")->string === "https://u:p%40ss@example.com/x", "password keeps single encoding through reassembly");

// The scheme is case-insensitive per RFC 3986 and is canonicalized to lowercase on parse.
$check(new URLComponents("HTTPS://example.com/a")->scheme === "https", "the scheme is canonicalized to lowercase");

// "0" is a valid value for a component; empty() used to swallow it.
$zero = new URLComponents("https://example.com/p?0");
$check($zero->query === "0", "a query of \"0\" survives parsing");
$check($zero->string === "https://example.com/p?0", "a query of \"0\" survives reassembly");

URLComponentsTestRunner::finish();
