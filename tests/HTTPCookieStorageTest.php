<?php

declare(strict_types=1);

namespace Sabatier\Foundation\Tests;

use Override;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Sabatier\Foundation\ArrayClass;
use Sabatier\Foundation\Date;
use Sabatier\Foundation\Dictionary;
use Sabatier\Foundation\Networking\HTTPCookie;
use Sabatier\Foundation\Networking\HTTPCookieAcceptPolicy;
use Sabatier\Foundation\Networking\HTTPCookieStorage;
use Sabatier\Foundation\URL;

final class HTTPCookieStorageTest extends TestCase
{
    private HTTPCookieStorage $storage;

    #[Override]
    protected function setUp(): void
    {
        $this->storage = HTTPCookieStorage::ephemeralStorage();
    }

    private function cookie(string $domain = "example.com", string $path = "/", string $name = "id", string $value = "1", bool $secure = false, ?Date $expires = null): HTTPCookie
    {
        return new HTTPCookie(new Dictionary([
            "Name" => $name, "Value" => $value, "Domain" => $domain,
            "Path" => $path, "Secure" => $secure, "Expires" => $expires,
        ]));
    }

    #[DataProvider("matchingURLs")]
    public function testURLSelection(string $domain, string $path, bool $secure, string $url, bool $matches): void
    {
        $this->storage->setCookie($this->cookie($domain, $path, secure: $secure));
        $this->assertSame($matches ? 1 : 0, $this->storage->cookies(new URL($url))?->count);
    }

    public static function matchingURLs(): array
    {
        return [
            ["example.com", "/", false, "https://example.com/", true],
            ["example.com", "/", false, "https://sub.example.com/", false],
            [".example.com", "/", false, "https://example.com/", true],
            [".example.com", "/", false, "https://sub.example.com/", true],
            [".example.com", "/", false, "https://badexample.com/", false],
            [".EXAMPLE.COM", "/", false, "https://sub.example.com/", true],
            ["example.com", "/account", false, "https://example.com/account", true],
            ["example.com", "/account", false, "https://example.com/account/profile", true],
            ["example.com", "/account", false, "https://example.com/accounts", false],
            ["example.com", "/account/", false, "https://example.com/account", false],
            ["example.com", "/account//", false, "https://example.com/account/profile", false],
            ["example.com", "/account", false, "https://example.com/other", false],
            ["example.com", "/", true, "http://example.com/", false],
            ["example.com", "/", true, "https://example.com/", true],
        ];
    }

    public function testReplacementAndDeletion(): void
    {
        $first = $this->cookie();
        $replacement = $this->cookie(value: "2");
        $otherPath = $this->cookie(path: "/account");
        $this->storage->setCookie($first);
        $this->storage->setCookie($replacement);
        $this->storage->setCookie($otherPath);
        $this->assertSame(2, $this->storage->cookies->count);
        $this->assertSame($replacement, $this->storage->cookies(new URL("https://example.com/"))[0]);
        $this->storage->deleteCookie($first);
        $this->assertSame(1, $this->storage->cookies->count);
        $this->assertSame($otherPath, $this->storage->cookies[0]);
    }

    public function testDistinctNameAndPathPairsDoNotCollide(): void
    {
        $this->storage->setCookie($this->cookie(path: "/a", name: "bc"));
        $this->storage->setCookie($this->cookie(path: "/ab", name: "c"));
        $this->assertSame(2, $this->storage->cookies->count);
        $this->storage->deleteCookie($this->cookie(path: "/a", name: "bc"));
        $this->assertSame("c", $this->storage->cookies[0]->name);
    }

    public function testExpiredCookieDeletesExistingValue(): void
    {
        $this->storage->setCookie($this->cookie());
        $this->storage->setCookie($this->cookie(expires: Date::dateWithTimeIntervalSince1970(946684800.0)));
        $this->assertSame(0, $this->storage->cookies->count);
    }

    public function testNeverPolicyAndIsolation(): void
    {
        $this->storage->cookieAcceptPolicy = HTTPCookieAcceptPolicy::never;
        $this->storage->setCookie($this->cookie());
        $this->storage->setCookies(new ArrayClass([$this->cookie()]), new URL("https://example.com"));
        $this->assertSame(0, $this->storage->cookies->count);
        $other = HTTPCookieStorage::ephemeralStorage();
        $other->setCookie($this->cookie());
        $this->assertSame(1, $other->cookies->count);
        $this->assertSame(0, $this->storage->cookies->count);
    }

    public function testResponseDomainValidation(): void
    {
        $valid = $this->cookie(domain: ".example.com");
        $this->storage->setCookies(new ArrayClass([$valid, $this->cookie(domain: "other.com")]), new URL("https://example.com"));
        $this->assertSame(1, $this->storage->cookies->count);
        $this->assertSame($valid, $this->storage->cookies[0]);
    }

    public function testRemoveCookiesByCreationDate(): void
    {
        $this->storage->setCookie($this->cookie());
        $this->storage->removeCookies(Date::dateWithTimeIntervalSince1970(2272147200.0));
        $this->assertSame(1, $this->storage->cookies->count);
        $this->storage->removeCookies(Date::dateWithTimeIntervalSince1970(946684800.0));
        $this->assertSame(0, $this->storage->cookies->count);
    }

    #[DataProvider("documentHosts")]
    public function testMainDocumentPolicy(string $documentHost, bool $accepted): void
    {
        $this->storage->cookieAcceptPolicy = HTTPCookieAcceptPolicy::onlyFromMainDocumentDomain;
        $this->storage->setCookies(new ArrayClass([$this->cookie()]), new URL("https://example.com"), new URL("https://$documentHost"));
        $this->assertSame($accepted ? 1 : 0, $this->storage->cookies->count);
    }

    public static function documentHosts(): array
    {
        return [["example.com", true], ["www.example.com", true], ["badexample.com", false], ["other.com", false]];
    }
}
