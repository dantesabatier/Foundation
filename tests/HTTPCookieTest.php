<?php

declare(strict_types=1);

namespace Sabatier\Foundation\Tests;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Sabatier\Foundation\ArrayClass;
use Sabatier\Foundation\Date;
use Sabatier\Foundation\Dictionary;
use Sabatier\Foundation\Networking\HTTPCookie;
use Sabatier\Foundation\URL;

final class HTTPCookieTest extends TestCase
{
    private function parse(string $header): HTTPCookie
    {
        $cookies = HTTPCookie::cookies(new Dictionary(["Set-Cookie" => $header]), new URL("https://example.com/account/login"));
        $this->assertSame(1, $cookies->count);
        return $cookies[0];
    }

    #[DataProvider("values")]
    public function testValuesArePreserved(string $value): void
    {
        $cookie = $this->parse("token=$value; Path=/");
        $this->assertSame("token", $cookie->name);
        $this->assertSame($value, $cookie->value);
        $this->assertSame("example.com", $cookie->domain);
    }

    public static function values(): array
    {
        return [[""], ["0"], ["abc=="], ["a%20b"]];
    }

    public function testAttributesAreCaseInsensitive(): void
    {
        $cookies = HTTPCookie::cookies(new Dictionary(["sEt-CoOkIe" => "id=1; pAtH=/account; sEcUrE; hTtPoNlY; sAmEsItE=Strict"]), new URL("https://example.com"));
        $this->assertSame(1, $cookies->count);
        $cookie = $cookies[0];
        $this->assertSame("/account", $cookie->path);
        $this->assertTrue($cookie->isSecure);
        $this->assertTrue($cookie->isHTTPOnly);
        $this->assertSame("Strict", $cookie->sameSitePolicy);
    }

    public function testSessionAndDefaultPath(): void
    {
        $cookie = $this->parse("id=1");
        $this->assertTrue($cookie->isSessionOnly);
        $this->assertNull($cookie->expiresDate);
        $this->assertSame("/account", $cookie->path);
    }

    #[DataProvider("attributeSeparators")]
    public function testEmptyAttributesDoNotStopScanning(string $header): void
    {
        $cookie = $this->parse($header);
        $this->assertSame("/account", $cookie->path);
        $this->assertTrue($cookie->isSecure);
        $this->assertTrue($cookie->isHTTPOnly);
    }

    public static function attributeSeparators(): array
    {
        return [
            ["id=1; Path=/account; Secure; HttpOnly;"],
            ["id=1;; Path=/account;; Secure;; HttpOnly"],
            ["id=1; ; Path=/account; ; Secure; ; HttpOnly"],
        ];
    }

    public function testExpiresIsAnAbsoluteDate(): void
    {
        $cookie = $this->parse("id=1; Expires=Wed, 01 Jan 2042 00:00:00 GMT");
        $this->assertSame(2272147200.0, $cookie->expiresDate?->timeIntervalSince1970);
        $this->assertFalse($cookie->isSessionOnly);
    }

    #[DataProvider("invalidAges")]
    public function testInvalidMaxAgeFallsBackToExpires(string $age): void
    {
        $cookie = $this->parse("id=1; Max-Age=$age; Expires=Wed, 01 Jan 2042 00:00:00 GMT");
        $this->assertSame(2272147200.0, $cookie->expiresDate?->timeIntervalSince1970);
    }

    public static function invalidAges(): array
    {
        return [[""], ["abc"], ["12x"], ["1.5"]];
    }

    #[DataProvider("ages")]
    public function testMaxAgeTakesPrecedence(string $age): void
    {
        $before = Date::now()->timeIntervalSince1970;
        $cookie = $this->parse("id=1; Max-Age=$age; Expires=Wed, 01 Jan 2042 00:00:00 GMT");
        $after = Date::now()->timeIntervalSince1970;
        $this->assertNotNull($cookie->expiresDate);
        $expiration = $cookie->expiresDate->timeIntervalSince1970;
        $this->assertGreaterThanOrEqual($before + (int)$age, $expiration);
        $this->assertLessThanOrEqual($after + (int)$age, $expiration);
        $this->assertFalse($cookie->isSessionOnly);
    }

    public static function ages(): array
    {
        return [["0"], ["-1"], ["3600"]];
    }

    public function testInvalidExpiresLeavesSessionCookie(): void
    {
        $cookie = $this->parse("id=1; Expires=invalid");
        $this->assertNull($cookie->expiresDate);
        $this->assertTrue($cookie->isSessionOnly);
    }

    #[DataProvider("invalidHeaders")]
    public function testMalformedCookiesAreIgnored(string $header): void
    {
        $this->assertSame(0, HTTPCookie::cookies(new Dictionary(["Set-Cookie" => $header]), new URL("https://example.com"))->count);
    }

    public static function invalidHeaders(): array
    {
        return [[""], ["missing-equals; Path=/"], ["=value; Path=/"]];
    }

    public function testExplicitDomainAndIPNormalization(): void
    {
        $this->assertSame(".example.com", $this->parse("id=1; Domain=EXAMPLE.COM")->domain);
        $cookies = HTTPCookie::cookies(new Dictionary(["Set-Cookie" => "id=1; Domain=127.0.0.1"]), new URL("http://127.0.0.1"));
        $this->assertSame("127.0.0.1", $cookies[0]->domain);
    }

    public function testRequestHeaderContainsOnlyNameValuePairs(): void
    {
        $cookies = new ArrayClass([$this->parse("id=1; Secure; HttpOnly"), $this->parse("token=abc==")]);
        $this->assertSame("id=1; token=abc==", HTTPCookie::requestHeaderFields($cookies)["Cookie"]);
        $this->assertSame(0, HTTPCookie::requestHeaderFields(new ArrayClass())->count);
    }

    public function testConstructorAcceptsStringVersionAndSessionDefaults(): void
    {
        $cookie = new HTTPCookie(new Dictionary(["Name" => "id", "Value" => "0", "Path" => "/", "Domain" => "example.com", "Version" => "1"]));
        $this->assertSame(1, $cookie->version);
        $this->assertTrue($cookie->isSessionOnly);
        $this->assertNull($cookie->expiresDate);
        $legacy = new HTTPCookie(new Dictionary(["Name" => "id", "Value" => "", "Path" => "/", "Domain" => "example.com"]));
        $this->assertSame(0, $legacy->version);
        $this->assertTrue($legacy->isSessionOnly);
    }
}
