<?php

declare(strict_types=1);

namespace Sabatier\Foundation\Tests;

use Override;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Sabatier\Foundation\Error;
use Sabatier\Foundation\Networking\URLResponse;
use Sabatier\Foundation\Networking\URLSession;
use Sabatier\Foundation\Networking\URLSessionConfiguration;
use Sabatier\Foundation\URL;
use const Sabatier\Foundation\URLErrorBadURL;
use const Sabatier\Foundation\URLErrorDomain;

/**
 * Regression tests for loading data URLs through URLSession.
 *
 * The URL must retain its opaque form so the protocol can be selected. The header delimiter
 * belongs to the URL syntax rather than the payload, valid empty and "0" bodies must not be
 * mistaken for failures, and malformed percent or Base64 input must fail the task exactly once.
 */
final class DataURLProtocolTest extends TestCase
{
    private URLSession $session;

    #[Override]
    protected function setUp(): void
    {
        $configuration = URLSessionConfiguration::ephemeral();
        $configuration->urlCache = null;
        $this->session = new URLSession($configuration);
    }

    /** @return array{string|null, URLResponse|null, Error|null} */
    private function load(string $url): array
    {
        $result = null;
        $calls = 0;
        $task = $this->session->dataTaskWithURL(new URL($url), function (?string $data, ?URLResponse $response, ?Error $error) use (&$calls, &$result): void {
            $calls += 1;
            $result = [$data, $response, $error];
        });
        $task->resume();
        $this->assertSame(1, $calls);
        $this->assertNotNull($result);
        $this->assertTrue($this->session->taskRegistry->isEmpty);
        return $result;
    }

    public function testLoadsPercentEncodedTextWithDefaultMetadata(): void
    {
        $url = new URL("DATA:,Hello%2C%20World!");
        $this->assertSame("data:,Hello%2C%20World!", $url->absoluteString);
        $this->assertSame("data", $url->scheme);
        [$data, $response, $error] = $this->load($url->absoluteString);
        $this->assertNull($error);
        $this->assertInstanceOf(URLResponse::class, $response);
        $this->assertSame("Hello, World!", $data);
        $this->assertSame("text/plain", $response->mimeType);
        $this->assertSame("US-ASCII", $response->textEncodingName);
        $this->assertSame(13, $response->expectedContentLength);
    }

    public function testLoadsExplicitMetadata(): void
    {
        [$data, $response, $error] = $this->load("data:text/html;charset=UTF-8,%3Cp%3Ehello%3C%2Fp%3E");
        $this->assertNull($error);
        $this->assertInstanceOf(URLResponse::class, $response);
        $this->assertSame("<p>hello</p>", $data);
        $this->assertSame("text/html", $response->mimeType);
        $this->assertSame("UTF-8", $response->textEncodingName);
        $this->assertSame(12, $response->expectedContentLength);
    }

    public function testLoadsMediaTypeWithoutParameters(): void
    {
        [$data, $response, $error] = $this->load("data:application/json,%7B%7D");
        $this->assertNull($error);
        $this->assertInstanceOf(URLResponse::class, $response);
        $this->assertSame("{}", $data);
        $this->assertSame("application/json", $response->mimeType);
        $this->assertNull($response->textEncodingName);
    }

    public function testLoadsBase64EncodedBinaryData(): void
    {
        [$data, $response, $error] = $this->load("data:application/octet-stream;base64,AAH/");
        $this->assertNull($error);
        $this->assertInstanceOf(URLResponse::class, $response);
        $this->assertSame("\x00\x01\xff", $data);
        $this->assertSame("application/octet-stream", $response->mimeType);
        $this->assertSame(3, $response->expectedContentLength);
    }

    /**
     * @param string $url
     * @param string $expected
     */
    #[DataProvider("validEmptyAndFalsyBodies")]
    public function testLoadsValidEmptyAndFalsyBodies(string $url, string $expected): void
    {
        [$data, $response, $error] = $this->load($url);
        $this->assertNull($error);
        $this->assertInstanceOf(URLResponse::class, $response);
        $this->assertSame($expected, $data);
        $this->assertSame(strlen($expected), $response->expectedContentLength);
    }

    public static function validEmptyAndFalsyBodies(): array
    {
        return [["data:,", ""], ["data:,0", "0"], ["data:;base64,", ""]];
    }

    /** @param string $url */
    #[DataProvider("malformedURLs")]
    public function testRejectsMalformedDataURLs(string $url): void
    {
        [$data, $response, $error] = $this->load($url);
        $this->assertNull($data);
        $this->assertNull($response);
        $this->assertInstanceOf(Error::class, $error);
        $this->assertSame(URLErrorDomain, $error->domain);
        $this->assertSame(URLErrorBadURL, $error->code);
    }

    public static function malformedURLs(): array
    {
        return [["data:text/plain"], ["data:,%ZZ"], ["data:;base64,SGVsbG8h!"]];
    }
}
