<?php

namespace Sabatier\Foundation\Test;

use PHPUnit\Framework\TestCase;
use Sabatier\Foundation\ArrayClass;
use Sabatier\Foundation\URL;
use Sabatier\Foundation\URLComponents;

final class URLComponentsTest extends TestCase
{
    public const URLString = "https://admin:admin@host.com:1234/path/data?key=value2#fragment";public URLComponents $components;

    protected function setUp(): void
    {
        parent::setUp();
        $this->components = new URLComponents(self::URLString);
    }

    public function testCanParseUrl(): void
    {
        self::assertInstanceOf(
            URL::class,
            $this->components->url
        );
    }

    public function testCanParseComponents(): void
    {
        self::assertNotNull($this->components->fragment);
        self::assertNotNull($this->components->host);
        self::assertNotNull($this->components->password);
        self::assertNotNull($this->components->path);
        self::assertIsInt($this->components->port);
        self::assertNotNull($this->components->query);
        self::assertNotNull($this->components->scheme);
        self::assertNotNull($this->components->user);
    }

    public function testCanParseQueryItems(): void
    {
        $queryItems = $this->components->queryItems;
        self::assertInstanceOf(
            ArrayClass::class,
            $queryItems
        );
        foreach ($queryItems as $queryItem) {
            self::assertIsString($queryItem->value);
        }
    }
}
