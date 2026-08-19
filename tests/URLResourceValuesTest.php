<?php

declare(strict_types=1);

namespace Sabatier\Foundation\Tests;

use PHPUnit\Framework\TestCase;
use Sabatier\Foundation\FileManager;
use Sabatier\Foundation\Set;
use Sabatier\Foundation\URL;
use Sabatier\Foundation\URLResourceKey;
use Throwable;

/**
 * Tests for the resource values URL exposes through URLResourceValuesStorage.
 *
 * isPackage answers whether a resource is a file package: a directory that carries an extension
 * and is therefore meant to be handled as one unit rather than descended into. Nothing registers
 * those extensions, so the shape of the name is what decides it — the same way a ".momd" or a
 * ".rtfd" is a package while a plain "Resources" directory is not.
 */
final class URLResourceValuesTest extends TestCase
{
    private string $directory;

    protected function setUp(): void
    {
        $this->directory = sys_get_temp_dir() . "/" . uniqid("resourcevalues", true);
        mkdir($this->directory, 0777, true);
    }

    protected function tearDown(): void
    {
        try {
            FileManager::default()->removeItem(URL::fileURL($this->directory));
        } catch (Throwable) {
        }
    }

    private function url(string $name): URL
    {
        return URL::fileURL($this->directory . "/" . $name);
    }

    private function makeDirectory(string $name): URL
    {
        mkdir($this->directory . "/" . $name, 0777, true);
        return $this->url($name);
    }

    private function makeFile(string $name): URL
    {
        file_put_contents($this->directory . "/" . $name, "contents");
        return $this->url($name);
    }

    private function isPackage(URL $url): mixed
    {
        $url->getResourceValue($value, URLResourceKey::isPackageKey);
        return $value;
    }

    public function testADirectoryWithAnExtensionIsAPackage(): void
    {
        $this->assertTrue($this->isPackage($this->makeDirectory("Service.momd")));
    }

    public function testADirectoryWithoutAnExtensionIsNotAPackage(): void
    {
        $this->assertFalse($this->isPackage($this->makeDirectory("Resources")));
    }

    /** An extension alone does not make a package: the resource has to be a directory. */
    public function testAFileWithAnExtensionIsNotAPackage(): void
    {
        $this->assertFalse($this->isPackage($this->makeFile("Service.mom")));
    }

    public function testIsPackageIsReportedAlongsideTheOtherKeys(): void
    {
        $url = $this->makeDirectory("Model.momd");
        $values = $url->resourceValues(new Set([URLResourceKey::isPackageKey, URLResourceKey::isDirectoryKey, URLResourceKey::isRegularFileKey]));

        $this->assertTrue($values->allValues[URLResourceKey::isPackageKey]);
        $this->assertTrue($values->allValues[URLResourceKey::isDirectoryKey]);
        $this->assertFalse($values->allValues[URLResourceKey::isRegularFileKey]);
    }
}
