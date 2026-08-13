<?php

declare(strict_types=1);

namespace Sabatier\Foundation\Tests;

use PHPUnit\Framework\TestCase;
use Sabatier\Foundation\Dictionary;
use Sabatier\Foundation\Networking\URLRequest;
use Sabatier\Foundation\URL;
use stdClass;

/**
 * Regression tests for URLRequest::getParsedBody().
 *
 * These tests pin down the decoding contract for an application/x-www-form-urlencoded
 * body: parse_str() already performs percent-decoding, so the body must be handed to it
 * exactly once. A percent-encoded structural character inside a value (for example a
 * literal "&" sent as "%26") must survive as data and must not be promoted into a
 * parameter separator, which would let a client smuggle additional parameters.
 *
 * They also pin the JSON contract: the body is decoded without the associative flag, so an
 * object stays a `stdClass` and a list an array. Decoded associatively the two are the same
 * empty array once empty, which erased the difference between `{}` and `[]` before the
 * collection conversion ever saw it.
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

    /**
     * Builds a JSON URLRequest carrying the given raw body.
     *
     * @param string|null $body The raw request body, or `null` when the request carries none.
     * @return URLRequest
     */
    private static function jsonRequest(?string $body): URLRequest
    {
        $request = new URLRequest(new URL("https://example.com/"));
        $request->allHTTPHeaderFields = new Dictionary(["Content-Type" => "application/json"]);
        $request->httpBody = $body;
        return $request;
    }

    public function testAJSONObjectIsDecodedAsAnObject(): void
    {
        $body = self::jsonRequest('{"tool":"get_server_time","arguments":{}}');
        $this->assertInstanceOf(stdClass::class, $body->getParsedBody(), "a JSON object body arrives as a stdClass, not an associative array");
    }

    public function testAnEmptyJSONObjectStaysDistinctFromAnEmptyList(): void
    {
        /** @var stdClass $decoded */
        $decoded = self::jsonRequest('{"arguments":{},"list":[]}')->getParsedBody();
        $this->assertInstanceOf(stdClass::class, $decoded->arguments, "an empty {} is an object");
        $this->assertSame([], $decoded->list, "an empty [] is a list, and the two do not collapse into each other");
    }

    public function testAJSONListBodyStaysAList(): void
    {
        $this->assertSame([1, 2, 3], self::jsonRequest("[1,2,3]")->getParsedBody(), "a JSON list body is an array");
    }

    public function testAnAbsentOrUndecodableBodyFallsBackToAnEmptyArray(): void
    {
        $this->assertSame([], self::jsonRequest(null)->getParsedBody(), "no body at all");
        $this->assertSame([], self::jsonRequest("")->getParsedBody(), "an empty body");
        $this->assertSame([], self::jsonRequest("{not json")->getParsedBody(), "a body that does not decode");
    }
}
