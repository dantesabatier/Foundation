<?php

namespace Sabatier\Foundation\Test;

use PHPUnit\Framework\TestCase;
use Sabatier\Foundation\UUID;
use function Sabatier\Foundation\uuid_generate_time;

class UUIDTest extends TestCase
{
    public const UIUIDString = 'e621e1f8-c36c-495a-93fc-0c247a3e6e5f';

    public function testCanBeCreatedFromUuid4(): UUID
    {
        $url = new UUID(self::UIUIDString);
        self::assertInstanceOf(
            UUID::class,
            $url
        );
        return $url;
    }

    public function testCanBeCreatedFromUuid1(): UUID
    {
        $url = new UUID(uuid_generate_time());
        self::assertInstanceOf(
            UUID::class,
            $url
        );
        return $url;
    }
    /**
     * @depends testCanBeCreatedFromUuid4
     * @param UUID $uuid
     */
    public function testCanBeUsedAsString(UUID $uuid): void
    {
        self::assertEquals(
            self::UIUIDString,
            $uuid
        );
    }
}
