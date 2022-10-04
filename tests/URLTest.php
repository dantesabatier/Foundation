<?php

namespace Sabatier\Foundation\Test;

use InvalidArgumentException;
use PHPUnit\Framework\TestCase;
use Sabatier\Foundation\URL;
use Sabatier\Foundation\URLScheme;

class URLTest extends TestCase
{
    public const URLString = 'http://localhost/user?id=1';

    public function testCanBeCreatedFromValidUrl(): URL
    {
        $url = new URL(self::URLString);
        self::assertInstanceOf(
            URL::class,
            $url
        );
        return $url;
    }

    public function testCannotBeCreatedFromInvalidUrl(): void
    {
        $this->expectException(InvalidArgumentException::class);
        new URL('invalid');
    }

    public function testCanBeCreatedFromValidFileUrl(): void
    {
        self::assertInstanceOf(
            URL::class,
            URL::fileURL('/')
        );
    }

    public function testCannotBeCreatedFromInvalidFileUrl(): void
    {
        $this->expectException(InvalidArgumentException::class);
        URL::fileURL('http://localhost');
    }

    /**
     * @depends testCanBeCreatedFromValidUrl
     * @param URL $url
     * @return URL
     */
    public function testCanReturnExpectedProperties(URL $url): URL
    {
        self::assertNull($url->fragment);
        self::assertEquals(
            URLScheme::http,
            $url->scheme
        );
        self::assertEquals(
            'localhost',
            $url->host
        );
        self::assertNull($url->port);
        self::assertNotNull($url->query);
        self::assertNull($url->user);
        self::assertNull($url->password);
        return $url;
    }

    /**
     * @depends testCanReturnExpectedProperties
     * @param URL $url
     */
    public function testCanBeUsedAsString(URL $url): void
    {
        self::assertEquals(
            self::URLString,
            $url
        );
    }
}
