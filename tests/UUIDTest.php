<?php

namespace Sabatier\Foundation\Test;

use PHPUnit\Framework\TestCase;
use Sabatier\Foundation\UUID;

use function Sabatier\Foundation\uuid_generate_time;

final class UUIDTest extends TestCase
{
    public const UUIDString = "e621e1f8-c36c-495a-93fc-0c247a3e6e5f";

    public function testCanBeCreatedFromUuid4(): UUID
    {
        $url = new UUID(self::UUIDString);
        self::assertInstanceOf(
            UUID::class,
            $url
        );
        return $url;
    }

    public function testCanBeCreatedFromUuid1(): void
    {
        self::assertInstanceOf(
            UUID::class,
            new UUID(uuid_generate_time())
        );
    }
    /**
     * @depends testCanBeCreatedFromUuid4
     * @param UUID $uuid
     */
    public function testCanBeUsedAsString(UUID $uuid): void
    {
        self::assertEquals(
            self::UUIDString,
            $uuid
        );
    }
}
