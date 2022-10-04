<?php

namespace Sabatier\Foundation\Test;

use InvalidArgumentException;
use PHPUnit\Framework\TestCase;
use Sabatier\Foundation\ArrayClass;
use Sabatier\Foundation\Bundle;
use Sabatier\Foundation\FileManager;
use Sabatier\Foundation\URL;
use const Sabatier\Foundation\kCFBundleNameKey;

class BundleTest extends TestCase
{
    public function testCanBeCreatedFromUrl(): Bundle
    {
        $bundle = Bundle::bundleWithURL(FileManager::default()->documentRootDirectory);
        self::assertInstanceOf(
            Bundle::class,
            $bundle
        );
        return $bundle;
    }

    public function testCannotBeCreatedFromInvalidUrl(): void
    {
        $this->expectException(InvalidArgumentException::class);
        Bundle::bundleWithURL(URL::fileURL(__FILE__));
    }

    public function testCanBeCreatedFromClass(): void
    {
        self::assertInstanceOf(
            Bundle::class,
            Bundle::bundleForClass(Bundle::class)
        );
    }

    /**
     * @depends testCanBeCreatedFromUrl
     * @param Bundle $bundle
     */
    public function testCanLoadInfoDictionary(Bundle $bundle): void
    {
        self::assertEquals(
            'Foundation',
            $bundle->object(kCFBundleNameKey)
        );
    }

    /**
     * @depends testCanBeCreatedFromUrl
     * @param Bundle $bundle
     */
    public function testCanLoadPreferredLocalizations(Bundle $bundle): void
    {
        self::assertInstanceOf(
            ArrayClass::class,
            $bundle->preferredLocalizations
        );
        self::assertTrue(
            !$bundle->preferredLocalizations->isEmpty()
        );
    }

    /**
     * @depends testCanBeCreatedFromUrl
     * @param Bundle $bundle
     */
    public function testCanLoadLocalizedString(Bundle $bundle): void
    {
        self::assertIsString($bundle->localizedString('An unexpected error has occurred'));
    }

    /**
     * @depends testCanBeCreatedFromUrl
     * @param Bundle $bundle
     */
    public function testCanFindResource(Bundle $bundle): void
    {
        self::assertInstanceOf(
            URL::class,
            $bundle->url('mime.types')
        );
    }

    /**
     * @depends testCanBeCreatedFromUrl
     * @param Bundle $bundle
     */
    public function testCanFindClass(Bundle $bundle): void
    {
        self::assertEquals(
            Bundle::class,
            $bundle->classNamed("Bundle")
        );
    }
}
