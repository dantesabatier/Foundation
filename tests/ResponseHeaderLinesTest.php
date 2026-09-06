<?php

declare(strict_types=1);

namespace Sabatier\Foundation\Tests;

use PHPUnit\Framework\TestCase;
use Sabatier\Foundation\ArrayClass;
use Sabatier\Foundation\Networking\ResponseHeaderLines;
use Sabatier\Foundation\URL;

final class ResponseHeaderLinesTest extends TestCase
{
    public function testHeadersAndMetadata(): void
    {
        $lines = new ResponseHeaderLines(new ArrayClass([
            "HTTP/1.1 302 Found",
            "Location: https://example.com:8443/next",
            "Date: Sat, 05 Sep 2026 12:34:56 GMT",
            "Content-Type: text/plain; charset=utf-8",
            "Content-Length: 12",
            "X-Empty:",
            "malformed",
        ]));
        $response = $lines->createHTTPURLResponse(new URL("https://example.com"));
        $this->assertNotNull($response);
        $this->assertSame(302, $response->statusCode);
        $this->assertSame("https://example.com:8443/next", $response->allHeaderFields["Location"]);
        $this->assertSame("Sat, 05 Sep 2026 12:34:56 GMT", $response->allHeaderFields["Date"]);
        $this->assertSame("", $response->allHeaderFields["X-Empty"]);
        $this->assertSame(5, $response->allHeaderFields->count);
        $this->assertSame("text/plain", $response->mimeType);
        $this->assertSame("utf-8", $response->textEncodingName);
        $this->assertSame(12, $response->expectedContentLength);
    }

    public function testEmptyHeadersHaveNoHTTPResponse(): void
    {
        $this->assertNull(new ResponseHeaderLines()->createHTTPURLResponse(new URL("https://example.com")));
    }

    public function testAppendedHeadersProduceResponse(): void
    {
        $lines = new ResponseHeaderLines()->byAppending("HTTP/2 204")->byAppending("X-Test: value");
        $response = $lines->createHTTPURLResponse(new URL("https://example.com"));
        $this->assertNotNull($response);
        $this->assertSame(204, $response->statusCode);
        $this->assertSame("value", $response->allHeaderFields["X-Test"]);
    }
}
