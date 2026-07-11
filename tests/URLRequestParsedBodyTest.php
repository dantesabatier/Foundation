<?php

declare(strict_types=1);

/**
 * Standalone regression tests for URLRequest::getParsedBody().
 *
 * Run with: php tests/URLRequestParsedBodyTest.php
 * Exits with a non-zero status code if any check fails.
 *
 * These tests pin down the decoding contract for an application/x-www-form-urlencoded
 * body: parse_str() already performs percent-decoding, so the body must be handed to it
 * exactly once. A percent-encoded structural character inside a value (for example a
 * literal "&" sent as "%26") must survive as data and must not be promoted into a
 * parameter separator, which would let a client smuggle additional parameters.
 */

namespace Sabatier\Foundation\Tests;

use Sabatier\Foundation\Dictionary;
use Sabatier\Foundation\Networking\URLRequest;
use Sabatier\Foundation\URL;

require __DIR__ . "/../vendor/autoload.php";

final class ParsedBodyTestRunner
{
    public static int $passed = 0;
    /** @var list<string> */
    public static array $failures = [];

    public static function check(bool $condition, string $message): void
    {
        if ($condition) {
            self::$passed++;
            return;
        }
        self::$failures[] = $message;
        fwrite(STDERR, "FAIL $message" . PHP_EOL);
    }

    public static function finish(): never
    {
        $failed = count(self::$failures);
        printf("%d passed, %d failed%s", self::$passed, $failed, PHP_EOL);
        exit($failed > 0 ? 1 : 0);
    }
}

/**
 * Builds a form-urlencoded URLRequest carrying the given raw body.
 *
 * @param string $body The raw request body exactly as it arrives on the wire.
 * @return URLRequest
 */
function form_request(string $body): URLRequest
{
    $request = new URLRequest(new URL("https://example.com/"));
    $request->allHTTPHeaderFields = new Dictionary(["Content-Type" => "application/x-www-form-urlencoded"]);
    $request->httpBody = $body;
    return $request;
}

$check = ParsedBodyTestRunner::check(...);

$simple = form_request("name=Ana&role=user");
$check($simple->getParsedBody() === ["name" => "Ana", "role" => "user"], "plain pairs are parsed into fields");

$encoded = form_request("name=Ana%20P%C3%A9rez");
$check($encoded->getParsedBody() === ["name" => "Ana Pérez"], "percent-encoded value is decoded exactly once");

$smuggled = form_request("name=admin%26role%3Droot");
$check($smuggled->getParsedBody() === ["name" => "admin&role=root"], "encoded separators inside a value stay as data, no smuggled parameter");

$plus = form_request("note=a%2Bb");
$check($plus->getParsedBody() === ["note" => "a+b"], "encoded plus sign is preserved as a literal plus");

ParsedBodyTestRunner::finish();
