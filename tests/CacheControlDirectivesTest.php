<?php

declare(strict_types=1);

namespace Sabatier\Foundation\Tests;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Sabatier\Foundation\Networking\CacheControlDirectives;

final class CacheControlDirectivesTest extends TestCase
{
    #[DataProvider("ageValues")]
    public function testAgeValues(string $value, ?int $expected): void
    {
        $directives = new CacheControlDirectives("max-age=$value, s-maxage=$value");
        $this->assertSame($expected, $directives->maxAge);
        $this->assertSame($expected, $directives->sharedMaxAge);
    }

    public static function ageValues(): array
    {
        return [
            "zero" => ["0", 0],
            "positive" => ["3600", 3600],
            "quoted" => ["\"60\"", 60],
            "quoted zero" => ["\"0\"", 0],
            "empty" => ["", null],
            "empty quotes" => ["\"\"", null],
            "unclosed quote" => ["\"60", null],
            "negative" => ["-1", null],
            "text" => ["abc", null],
            "numeric prefix" => ["60abc", null],
            "fraction" => ["1.5", null],
        ];
    }

    public function testCombinedDirectives(): void
    {
        $directives = new CacheControlDirectives(" public, NO-CACHE, No-Store, MAX-AGE=60, s-maxage=120 ");
        $this->assertTrue($directives->noCache);
        $this->assertTrue($directives->noStore);
        $this->assertSame(60, $directives->maxAge);
        $this->assertSame(120, $directives->sharedMaxAge);
    }

    public function testDefaults(): void
    {
        $directives = new CacheControlDirectives("");
        $this->assertNull($directives->maxAge);
        $this->assertNull($directives->sharedMaxAge);
        $this->assertFalse($directives->noCache);
        $this->assertFalse($directives->noStore);
    }
}
