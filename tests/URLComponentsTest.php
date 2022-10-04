<?php

namespace Sabatier\Foundation\Test;

use PHPUnit\Framework\TestCase;
use Sabatier\Foundation\ArrayClass;
use Sabatier\Foundation\URL;
use Sabatier\Foundation\URLComponents;

final class URLComponentsTest extends TestCase
{
    public const URLString = 'https://admin:admin@host.com:1234/path/data?key=value2#fragment';

    public URLComponents $components;

    protected function setUp(): void
    {
        parent::setUp();
        $this->components = new URLComponents(self::URLString);
    }

    public function testCanGetUrl(): void
    {
        self::assertInstanceOf(
            URL::class,
            $this->components->url
        );
    }

    public function testCanGetQueryItems(): void
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
