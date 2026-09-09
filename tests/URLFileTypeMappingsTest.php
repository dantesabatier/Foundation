<?php

declare(strict_types=1);

namespace Sabatier\Foundation\Tests;

use PHPUnit\Framework\TestCase;
use Sabatier\Foundation\URLFileTypeMappings;

final class URLFileTypeMappingsTest extends TestCase
{
    public function testSharedReturnsTheSameInstance(): void
    {
        $this->assertSame(URLFileTypeMappings::shared(), URLFileTypeMappings::shared());
    }

    public function testExtensionsPreserveTheDeclaredPreferenceOrder(): void
    {
        $mappings = new URLFileTypeMappings();

        $this->assertSame(["jpeg", "jpg", "jpe"], $mappings->extensions("image/jpeg")?->array);
        $this->assertSame("jpeg", $mappings->preferredExtension("image/jpeg"));
    }

    public function testLookupsAreCaseInsensitive(): void
    {
        $mappings = new URLFileTypeMappings();

        $this->assertSame(["json"], $mappings->extensions("APPLICATION/JSON")?->array);
        $this->assertSame("image/jpeg", $mappings->mimeType("JPG"));
    }

    public function testExtensionLookupReturnsTheMappedMIMEType(): void
    {
        $mappings = new URLFileTypeMappings();

        $this->assertSame("text/plain", $mappings->mimeType("txt"));
        $this->assertSame("text/html", $mappings->mimeType("html"));
    }

    public function testUnknownValuesReturnNull(): void
    {
        $mappings = new URLFileTypeMappings();

        $this->assertNull($mappings->extensions("application/x-unknown"));
        $this->assertNull($mappings->preferredExtension("application/x-unknown"));
        $this->assertNull($mappings->mimeType("unknown-extension"));
    }
}
