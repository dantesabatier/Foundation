<?php
/** @noinspection PhpArrayWriteIsNotUsedInspection */

declare(strict_types=1);

namespace Sabatier\Foundation\Tests;

use Override;
use PHPUnit\Framework\TestCase;

use function Sabatier\Foundation\getallheaders;
use function Sabatier\Foundation\is_parseable_url;
use function Sabatier\Foundation\request_url;
use function Sabatier\Foundation\url_encode;

/**
 * Exercises URL helpers whose behavior depends on scheme syntax or the request environment.
 */
final class URLAdditionsTest extends TestCase
{
    /** @var array<string, mixed> */
    private array $server;

    #[Override]
    protected function setUp(): void
    {
        $this->server = $_SERVER;
        $_SERVER = [];
    }

    #[Override]
    protected function tearDown(): void
    {
        $_SERVER = $this->server;
    }

    public function testParseableURLRecognizesSupportedSyntax(): void
    {
        foreach ([
            "https://example.test",
            "WSS://example.test/socket",
            "file:///tmp/file.txt",
            "php://input",
            "x-coredata://store/object",
            "data:,hello",
            "DATA:text/plain;charset=UTF-8,hello",
        ] as $url) {
            $this->assertTrue(is_parseable_url($url), $url);
        }
    }

    public function testParseableURLRejectsUnsupportedOrMisplacedSchemes(): void
    {
        foreach ([
            "",
            "https:example.test",
            "data",
            "datax:,hello",
            "*data:,hello*",
            "javascript:alert(1)",
        ] as $url) {
            $this->assertFalse(is_parseable_url($url), $url);
        }
    }

    public function testURLEncodeAppendsEndpointAndParameters(): void
    {
        $this->assertSame("https://example.test/api/users", url_encode("https://example.test/api", "users"));
        $this->assertSame("https://example.test/api/users", url_encode("https://example.test/api/", "users"));
        $this->assertSame("https://example.test/api/search?q=hello+world&page=2", url_encode("https://example.test/api", "search", ["q" => "hello world", "page" => 2]));
    }

    public function testRequestURLPreservesTheCompleteRequestTarget(): void
    {
        $_SERVER = [
            "HTTPS" => "on",
            "HTTP_HOST" => "example.test:8443",
            "REQUEST_URI" => "/search?q=what?now",
        ];
        $this->assertSame("https://example.test:8443/search?q=what?now", request_url());
    }

    public function testRequestURLTreatsDisabledHTTPSAsHTTP(): void
    {
        $_SERVER = [
            "HTTPS" => "off",
            "HTTP_HOST" => "example.test",
            "REQUEST_URI" => "/status",
        ];
        $this->assertSame("http://example.test/status", request_url());
    }

    public function testRequestURLRequiresRequestComponents(): void
    {
        $this->assertSame("", request_url());
        $_SERVER["HTTP_HOST"] = "example.test";
        $this->assertSame("", request_url());
        $_SERVER = ["REQUEST_URI" => "/status"];
        $this->assertSame("", request_url());
    }

    public function testGetAllHeadersNormalizesNamesAndDedicatedContentValues(): void
    {
        $_SERVER = [
            "HTTP_ACCEPT_LANGUAGE" => "es-MX",
            "HTTP_X_REQUEST_ID" => "request-1",
            "HTTP_CONTENT_TYPE" => "ignored",
            "CONTENT_TYPE" => "application/json",
            "CONTENT_LENGTH" => "42",
            "CONTENT_MD5" => "digest",
            "SERVER_NAME" => "ignored.test",
        ];
        $this->assertSame([
            "Accept-Language" => "es-MX",
            "X-Request-Id" => "request-1",
            "Content-Type" => "application/json",
            "Content-Length" => "42",
            "Content-Md5" => "digest",
        ], getallheaders());
    }

    public function testGetAllHeadersUsesAvailableAuthorizationRepresentation(): void
    {
        $_SERVER = [
            "HTTP_AUTHORIZATION" => "Bearer direct",
            "REDIRECT_HTTP_AUTHORIZATION" => "Bearer redirected",
            "PHP_AUTH_USER" => "user",
            "PHP_AUTH_PW" => "password",
            "PHP_AUTH_DIGEST" => "Digest fallback",
        ];
        $this->assertSame("Bearer direct", getallheaders()["Authorization"]);

        unset($_SERVER["HTTP_AUTHORIZATION"]);
        $this->assertSame("Bearer redirected", getallheaders()["Authorization"]);

        unset($_SERVER["REDIRECT_HTTP_AUTHORIZATION"]);
        $this->assertSame("Basic " . base64_encode("user:password"), getallheaders()["Authorization"]);

        unset($_SERVER["PHP_AUTH_USER"], $_SERVER["PHP_AUTH_PW"]);
        $this->assertSame("Digest fallback", getallheaders()["Authorization"]);
    }
}
