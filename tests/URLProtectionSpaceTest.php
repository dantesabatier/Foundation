<?php

declare(strict_types=1);

namespace Sabatier\Foundation\Tests;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Sabatier\Foundation\Dictionary;
use Sabatier\Foundation\Networking\HTTPStatusCode;
use Sabatier\Foundation\Networking\HTTPURLResponse;
use Sabatier\Foundation\Networking\URLProtectionSpace;
use Sabatier\Foundation\URL;
use const Sabatier\Foundation\Networking\URLAuthenticationMethodHTTPBasic;
use const Sabatier\Foundation\Networking\URLAuthenticationMethodNTLM;

final class URLProtectionSpaceTest extends TestCase
{
    #[DataProvider("security")]
    public function testCredentialSecurityDependsOnProtocolAndAuthentication(string $protocol, string $method, bool $secure): void
    {
        $space = new URLProtectionSpace("example.com", protocol: $protocol, authenticationMethod: $method);
        $this->assertSame($secure, $space->receivesCredentialSecurely);
        $this->assertFalse($space->isProxy);
    }

    public static function security(): array
    {
        return [
            ["http", URLAuthenticationMethodHTTPBasic, false],
            ["https", URLAuthenticationMethodHTTPBasic, true],
            ["ftps", URLAuthenticationMethodHTTPBasic, true],
            ["http", URLAuthenticationMethodNTLM, true],
        ];
    }

    public function testSerializationPreservesTheProtectionBoundary(): void
    {
        $space = new URLProtectionSpace("proxy.example.com", 8080, "HTTP", "http", "members", URLAuthenticationMethodHTTPBasic);
        $copy = unserialize(serialize($space));
        $this->assertInstanceOf(URLProtectionSpace::class, $copy);
        $this->assertSame($space->__serialize(), $copy->__serialize());
        $this->assertTrue($copy->isProxy);
    }

    public function testBasicChallengeCreatesAProtectionSpaceForTheResponseOrigin(): void
    {
        $response = new HTTPURLResponse(new URL("https://example.com/private"), HTTPStatusCode::unauthorized, headerFields: new Dictionary(["WWW-Authenticate" => "Basic realm=\"members\""]));
        $space = URLProtectionSpace::create($response);
        $this->assertInstanceOf(URLProtectionSpace::class, $space);
        $this->assertSame("example.com", $space->host);
        $this->assertSame(443, $space->port);
        $this->assertSame("members", $space->realm);
        $this->assertSame(URLAuthenticationMethodHTTPBasic, $space->authenticationMethod);
    }

    public function testResponseWithoutChallengeDoesNotCreateAProtectionSpace(): void
    {
        $response = new HTTPURLResponse(new URL("https://example.com/private"), HTTPStatusCode::unauthorized);
        $this->assertNull(URLProtectionSpace::create($response));
    }
}
