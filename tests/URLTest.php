<?php

namespace Sabatier\Foundation\Test;

use Exception;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;
use Sabatier\Foundation\Set;
use Sabatier\Foundation\URL;
use Sabatier\Foundation\URLResourceKey;

final class URLTest extends TestCase
{
    public const URLString = "http://localhost";

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
        new URL("invalid");
    }

    /**
     * @depends testCanBeCreatedFromValidUrl
     * @param URL $url
     */
    public function testCanBeUsedAsString(URL $url): void
    {
        self::assertEquals(
            self::URLString,
            $url
        );
    }

    public function testCanBeCreatedFromValidFileUrl(): URL
    {
        $url = URL::fileURL(__FILE__);
        self::assertInstanceOf(
            URL::class,
            $url
        );
        return $url;
    }

    public function testCannotBeCreatedFromInvalidFileUrl(): void
    {
        $this->expectException(InvalidArgumentException::class);
        URL::fileURL("http://localhost");
    }

    /**
     * @depends testCanBeCreatedFromValidFileUrl
     * @param URL $url
     * @return URL
     * @throws Exception
     */
    public function testCanSetTemporaryResourceValues(URL $url): URL
    {
        $keys = new Set([URLResourceKey::nameKey]);
        $values = $url->resourceValues($keys);
        foreach ($values->allValues as $key => $value) {
            $url->setTemporaryResourceValue($value, $key);
        }
        self::assertIsString($values->name);
        return $url;
    }
}
