<?php

declare(strict_types=1);

namespace Sabatier\Foundation\Tests;

use Override;
use PHPUnit\Framework\TestCase;

use function Sabatier\Foundation\parse_env_file;

/**
 * Exercises .env parsing without changing the process environment.
 */
final class ENVAdditionsTest extends TestCase
{
    private string $path;

    #[Override]
    protected function setUp(): void
    {
        $this->path = sys_get_temp_dir() . DIRECTORY_SEPARATOR . uniqid("sabatier-foundation-env-", true) . ".env";
        @unlink($this->path);
    }

    #[Override]
    protected function tearDown(): void
    {
        @unlink($this->path);
    }

    private function write(string $contents): void
    {
        file_put_contents($this->path, $contents);
    }

    public function testMissingFileProducesAnEmptyEnvironment(): void
    {
        $this->assertSame([], parse_env_file($this->path));
    }

    public function testParsesValuesPrefixesQuotesAndDuplicateKeys(): void
    {
        $this->write(<<<'ENV'
# comment
PLAIN=value
SPACED = value with spaces
export EXPORTED=ready
DOUBLE="  preserved  "
SINGLE='single value'
EMPTY=
EQUALS=left=right
HASH="value # retained"
DUPLICATE=first
DUPLICATE=second
ENV);
        $this->assertSame([
            "PLAIN" => "value",
            "SPACED" => "value with spaces",
            "EXPORTED" => "ready",
            "DOUBLE" => "  preserved  ",
            "SINGLE" => "single value",
            "EMPTY" => "",
            "EQUALS" => "left=right",
            "HASH" => "value # retained",
            "DUPLICATE" => "second",
        ], parse_env_file($this->path));
    }

    public function testIgnoresBlankCommentsAndMalformedLines(): void
    {
        $this->write("\r\n  # comment\r\nINVALID\r\n=missing-key\r\n   =also-missing\r\nexport \r\n");
        $this->assertSame([], parse_env_file($this->path));
    }

    public function testOnlyMatchingOuterQuotesAreRemoved(): void
    {
        $this->write(<<<'ENV'
DOUBLE="value"
SINGLE='value'
MISMATCHED="value'
ONE_QUOTE="
ENV);
        $this->assertSame([
            "DOUBLE" => "value",
            "SINGLE" => "value",
            "MISMATCHED" => "\"value'",
            "ONE_QUOTE" => "\"",
        ], parse_env_file($this->path));
    }
}
