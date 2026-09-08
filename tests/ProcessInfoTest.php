<?php

declare(strict_types=1);

namespace Sabatier\Foundation\Tests;

use Override;
use PHPUnit\Framework\TestCase;
use ReflectionProperty;
use Sabatier\Foundation\FileManager;
use Sabatier\Foundation\ProcessInfo;

use function Sabatier\Foundation\full_user_name;
use function Sabatier\Foundation\process_name;
use function Sabatier\Foundation\user_name;

final class ProcessInfoTest extends TestCase
{
    private string $root;
    /** @var array<string, mixed> */
    private array $server;
    private ReflectionProperty $processInfoProperty;
    private ReflectionProperty $fileManagerProperty;
    private ?ProcessInfo $originalProcessInfo;
    private ?FileManager $originalFileManager;

    #[Override]
    protected function setUp(): void
    {
        $this->root = sys_get_temp_dir() . DIRECTORY_SEPARATOR . uniqid("sabatier-foundation-process-info-", true);
        mkdir($this->root, 0777, true);
        $this->server = $_SERVER;
        $_SERVER["PWD"] = $this->root;

        $this->processInfoProperty = new ReflectionProperty(ProcessInfo::class, "processInfo");
        /** @var ProcessInfo|null $processInfo */
        $processInfo = $this->processInfoProperty->getValue();
        $this->originalProcessInfo = $processInfo;
        $this->processInfoProperty->setValue(null, null);

        $this->fileManagerProperty = new ReflectionProperty(FileManager::class, "default");
        /** @var FileManager|null $fileManager */
        $fileManager = $this->fileManagerProperty->getValue();
        $this->originalFileManager = $fileManager;
        $this->fileManagerProperty->setValue(null, null);
    }

    #[Override]
    protected function tearDown(): void
    {
        $this->processInfoProperty->setValue(null, $this->originalProcessInfo);
        $this->fileManagerProperty->setValue(null, $this->originalFileManager);
        $_SERVER = $this->server;
        @unlink($this->root . DIRECTORY_SEPARATOR . ".env");
        @rmdir($this->root);
    }

    public function testProcessInfoReturnsTheSharedInstance(): void
    {
        $processInfo = ProcessInfo::processInfo();
        $this->assertSame($processInfo, ProcessInfo::processInfo());
    }

    public function testArgumentsAreCapturedWhenFirstRead(): void
    {
        $_SERVER["argv"] = ["foundation", "--mode", "test"];
        $processInfo = new ProcessInfo();
        $arguments = $processInfo->arguments;

        $this->assertSame(["foundation", "--mode", "test"], $arguments->array);
        $_SERVER["argv"] = ["changed"];
        $this->assertSame($arguments, $processInfo->arguments);
        $this->assertSame(["foundation", "--mode", "test"], $processInfo->arguments->array);
    }

    public function testMissingArgumentsProduceAnEmptyCollection(): void
    {
        unset($_SERVER["argv"]);
        $this->assertSame([], new ProcessInfo()->arguments->array);
    }

    public function testEnvironmentIsParsedAndCapturedWhenFirstRead(): void
    {
        file_put_contents($this->root . DIRECTORY_SEPARATOR . ".env", "APP_ENV=test\nEMPTY=\n");
        $processInfo = new ProcessInfo();
        $environment = $processInfo->environment;

        $this->assertSame(["APP_ENV" => "test", "EMPTY" => ""], $environment->array);
        file_put_contents($this->root . DIRECTORY_SEPARATOR . ".env", "APP_ENV=production\n");
        $this->assertSame($environment, $processInfo->environment);
        $this->assertSame(["APP_ENV" => "test", "EMPTY" => ""], $processInfo->environment->array);
    }

    public function testGloballyUniqueStringIsStableAndHasTheExpectedFormat(): void
    {
        $processInfo = new ProcessInfo();
        $identifier = $processInfo->globallyUniqueString;

        $this->assertMatchesRegularExpression("/^[0-9a-f]{32}$/", $identifier);
        $this->assertSame($identifier, $processInfo->globallyUniqueString);
        $this->assertNotSame($identifier, new ProcessInfo()->globallyUniqueString);
    }

    public function testProcessAndHostPropertiesReflectTheirSystemSources(): void
    {
        $processInfo = new ProcessInfo();

        $this->assertSame(getmypid() ?: 0, $processInfo->processIdentifier);
        $this->assertSame(process_name(), $processInfo->processName);
        $this->assertSame(user_name(), $processInfo->userName);
        $this->assertSame(full_user_name(), $processInfo->fullUserName);
        $this->assertSame(gethostname() ?: "localhost", $processInfo->hostName);
    }

    public function testSystemUptimeReadsTheMonotonicClock(): void
    {
        $processInfo = new ProcessInfo();
        $before = hrtime(true) / 1e9;
        $systemUptime = $processInfo->systemUptime;
        $after = hrtime(true) / 1e9;

        $this->assertGreaterThanOrEqual($before, $systemUptime);
        $this->assertLessThanOrEqual($after, $systemUptime);
    }
}
