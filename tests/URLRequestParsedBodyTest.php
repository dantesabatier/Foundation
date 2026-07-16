<?php

declare(strict_types=1);

namespace Sabatier\Foundation\Tests;

use PHPUnit\Framework\TestCase;
use Sabatier\Foundation\Dictionary;
use Sabatier\Foundation\Networking\URLRequest;
use Sabatier\Foundation\URL;

/**
 * Regression tests for URLRequest::getParsedBody().
 *
 * These tests pin down the decoding contract for an application/x-www-form-urlencoded
 * body: parse_str() already performs percent-decoding, so the body must be handed to it
 * exactly once. A percent-encoded structural character inside a value (for example a
 * literal "&" sent as "%26") must survive as data and must not be promoted into a
 * parameter separator, which would let a client smuggle additional parameters.
 */
final class URLRequestParsedBodyTest extends TestCase
{
    /**
     * Builds a form-urlencoded URLRequest carrying the given raw body.
     *
     * @param string $body The raw request body exactly as it arrives on the wire.
     * @return URLRequest
     */
    private static function formRequest(string $body): URLRequest
    {
        $request = new URLRequest(new URL("https://example.com/"));
        $request->allHTTPHeaderFields = new Dictionary(["Content-Type" => "application/x-www-form-urlencoded"]);
        $request->httpBody = $body;
        return $request;
    }

    public function testPlainPairsAreParsedIntoFields(): void
    {
        $simple = self::formRequest("name=Ana&role=user");
        $this->assertSame(["name" => "Ana", "role" => "user"], $simple->getParsedBody(), "plain pairs are parsed into fields");
    }

    public function testPercentEncodedValueIsDecodedExactlyOnce(): void
    {
        $encoded = self::formRequest("name=Ana%20P%C3%A9rez");
        $this->assertSame(["name" => "Ana Pérez"], $encoded->getParsedBody(), "percent-encoded value is decoded exactly once");
    }

    public function testEncodedSeparatorsInsideAValueStayAsData(): void
    {
        $smuggled = self::formRequest("name=admin%26role%3Droot");
        $this->assertSame(["name" => "admin&role=root"], $smuggled->getParsedBody(), "encoded separators inside a value stay as data, no smuggled parameter");
    }

    public function testEncodedPlusSignIsPreservedAsALiteralPlus(): void
    {
        $plus = self::formRequest("note=a%2Bb");
        $this->assertSame(["note" => "a+b"], $plus->getParsedBody(), "encoded plus sign is preserved as a literal plus");
    }
}
