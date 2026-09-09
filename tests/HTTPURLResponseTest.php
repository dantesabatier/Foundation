<?php

declare(strict_types=1);

namespace Sabatier\Foundation\Tests;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Sabatier\Foundation\Dictionary;
use Sabatier\Foundation\Networking\HTTPStatusCode;
use Sabatier\Foundation\Networking\HTTPURLResponse;
use Sabatier\Foundation\URL;

use const Sabatier\Foundation\Networking\URLResponseUnknownLength;

/**
 * Tests the header parsing src/Networking/HTTPURLResponse.php performs in its
 * constructor. None of it needs a transport: the response is built from a URL and a
 * header dictionary, which is exactly how the protocol handlers construct it once a
 * transfer's header is complete.
 *
 * Regression guards:
 *  - Content-Type is split into its mime type and its charset, and a type without
 *    parameters leaves the encoding unset rather than guessing one;
 *  - Content-Length populates expectedContentLength, and its absence reports the
 *    unknown-length sentinel instead of zero — a zero-length body and an unknown one
 *    are different answers;
 *  - Content-Disposition yields the filename, falling back to "Unknown";
 *  - header names are canonicalized, except the X- prefixed ones, which keep the
 *    casing the server sent, and WWW-Authenticate, which is pinned to its usual form;
 *  - lookups are case-insensitive whatever the stored casing.
 */
final class HTTPURLResponseTest extends TestCase
{
    private function url(): URL
    {
        return new URL("https://example.test/resource");
    }

    /** @param array<string, string> $headers */
    private function response(array $headers, int $statusCode = HTTPStatusCode::ok): HTTPURLResponse
    {
        return new HTTPURLResponse($this->url(), $statusCode, "HTTP/1.1", new Dictionary($headers));
    }

    public function testAResponseWithoutHeadersReportsItsUnknowns(): void
    {
        $response = new HTTPURLResponse($this->url());

        $this->assertSame(HTTPStatusCode::ok, $response->statusCode, "the status defaults to 200");
        $this->assertNull($response->mimeType);
        $this->assertNull($response->textEncodingName);
        $this->assertSame(URLResponseUnknownLength, $response->expectedContentLength, "an absent length is unknown, not zero");
        $this->assertSame("Unknown", $response->suggestedFilename);
    }

    public function testContentTypeIsSplitIntoMimeTypeAndEncoding(): void
    {
        $response = $this->response(["Content-Type" => "text/html; charset=utf-8"]);

        $this->assertSame("text/html", $response->mimeType);
        $this->assertSame("utf-8", $response->textEncodingName);
    }

    public function testAContentTypeWithoutParametersLeavesTheEncodingUnset(): void
    {
        $response = $this->response(["Content-Type" => "application/json"]);

        $this->assertSame("application/json", $response->mimeType);
        $this->assertNull($response->textEncodingName, "no charset is reported rather than a default");
    }

    public function testContentLengthPopulatesTheExpectedLength(): void
    {
        $this->assertSame(1234, $this->response(["Content-Length" => "1234"])->expectedContentLength);
    }

    public function testAZeroContentLengthIsReportedAsUnknown(): void
    {
        // The header is read through a truthiness check, so the string "0" is indistinguishable from an absent header and an empty body reports the unknown-length sentinel. Pinned as the current behaviour: telling the two apart would mean reading the header explicitly.
        $this->assertSame(URLResponseUnknownLength, $this->response(["Content-Length" => "0"])->expectedContentLength);
    }

    public function testContentDispositionYieldsTheSuggestedFilename(): void
    {
        $response = $this->response(["Content-Disposition" => "attachment; filename=report.pdf"]);

        $this->assertSame("report.pdf", $response->suggestedFilename);
    }

    public function testADispositionWithoutParametersFallsBackToUnknown(): void
    {
        $this->assertSame("Unknown", $this->response(["Content-Disposition" => "attachment"])->suggestedFilename);
    }

    public function testPrefixedAndAuthenticateHeadersKeepTheirOwnCasing(): void
    {
        $response = $this->response([
            "x-custom-Header" => "kept",
            "www-authenticate" => "Basic realm=x",
            "some-other" => "canonicalized",
        ]);

        $keys = array_keys($response->allHeaderFields->array);

        $this->assertContains("x-custom-Header", $keys, "an X- header is stored exactly as the server sent it");
        $this->assertContains("WWW-Authenticate", $keys, "the authenticate header is pinned to its usual casing");
        $this->assertContains("Some-other", $keys, "every other header is canonicalized");
    }

    /** @return iterable<string, array{string}> */
    public static function lookupCasingProvider(): iterable
    {
        yield "as sent" => ["content-type"];
        yield "upper" => ["CONTENT-TYPE"];
        yield "canonical" => ["Content-Type"];
        yield "mixed" => ["CoNtEnT-tYpE"];
    }

    #[DataProvider("lookupCasingProvider")]
    public function testHeaderLookupIgnoresCasing(string $field): void
    {
        $response = $this->response(["content-type" => "text/plain"]);

        $this->assertSame("text/plain", $response->valueForHttpHeaderField($field));
    }

    public function testAnAbsentHeaderReadsAsNull(): void
    {
        $this->assertNull($this->response(["Content-Type" => "text/plain"])->valueForHttpHeaderField("X-Missing"));
    }

    public function testTheHttpVersionDefaultsWhenNoneIsGiven(): void
    {
        $this->assertSame("HTTP/2", new HTTPURLResponse($this->url(), HTTPStatusCode::ok, "HTTP/2")->httpVersion);
        $this->assertNotSame("", new HTTPURLResponse($this->url())->httpVersion, "a version is always reported");
    }

    /** @return iterable<string, array{int, string}> */
    public static function statusCodeProvider(): iterable
    {
        yield "ok" => [HTTPStatusCode::ok, "OK"];
        yield "not found" => [HTTPStatusCode::notFound, "Not found"];
        yield "unknown code" => [599, "Server Error"];
    }

    #[DataProvider("statusCodeProvider")]
    public function testAStatusCodeHasALocalizedDescription(int $statusCode, string $expected): void
    {
        $this->assertSame($expected, HTTPURLResponse::localizedString($statusCode));
    }

    public function testTheStatusCodeIsReportedAsGiven(): void
    {
        $this->assertSame(HTTPStatusCode::notFound, $this->response([], HTTPStatusCode::notFound)->statusCode);
    }
}
