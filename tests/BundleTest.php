<?php

declare(strict_types=1);

namespace Sabatier\Foundation\Tests;

use GdImage;
use Override;
use PHPUnit\Framework\TestCase;
use Sabatier\Foundation\ArrayClass;
use Sabatier\Foundation\Bundle;
use Sabatier\Foundation\Dictionary;
use Sabatier\Foundation\URL;

use const Sabatier\Foundation\kCFBundleNameKey;

/**
 * Tests for src/Bundle.php.
 *
 * Regression guards:
 *  - urls()/paths() must return matches when no resource name is given (the name
 *    filter used to compare every file against an empty string and matched nothing);
 *  - bundleForClass() must terminate for classes outside a "src" tree on Windows,
 *    where paths bottom out at a drive root ("C:/") instead of "/" — a regression
 *    here manifests as this test hanging;
 *  - executableURL must return null (not TypeError) when Info.plist is missing;
 *  - image() must return null (not false) for an unreadable image file.
 */
final class BundleTest extends TestCase
{
    private string $root;
    private string $bundleRoot;
    private string $bareRoot;
    private string $resources;
    private Bundle $bundle;
    private Bundle $bare;

    #[Override]
    protected function setUp(): void
    {
        // Fixture: a bundle directory with an Info.plist and a Resources tree, plus a bare directory with neither.
        $this->root = sys_get_temp_dir() . DIRECTORY_SEPARATOR . "sabatier-bundle-test-" . (int)getmypid();
        $this->bundleRoot = $this->root . DIRECTORY_SEPARATOR . "TestBundle";
        $this->bareRoot = $this->root . DIRECTORY_SEPARATOR . "BareBundle";
        $this->resources = $this->bundleRoot . DIRECTORY_SEPARATOR . "Resources";
        if (!is_dir($this->resources . DIRECTORY_SEPARATOR . "Extras")) {
            mkdir($this->resources . DIRECTORY_SEPARATOR . "Extras", 0777, true);
        }
        if (!is_dir($this->resources . DIRECTORY_SEPARATOR . "es")) {
            mkdir($this->resources . DIRECTORY_SEPARATOR . "es", 0777, true);
        }
        if (!is_dir($this->bareRoot)) {
            mkdir($this->bareRoot, 0777, true);
        }

        $info = [
            '<?xml version="1.0" encoding="UTF-8"?>',
            '<!DOCTYPE plist PUBLIC "-//Apple//DTD PLIST 1.0//EN" "https://www.apple.com/DTDs/PropertyList-1.0.dtd">',
            "<plist>",
            "<dict>",
            "\t<key>CFBundleDevelopmentRegion</key>",
            "\t<string>en</string>",
            "\t<key>CFBundleIdentifier</key>",
            "\t<string>com.example.bundle-tests</string>",
            "\t<key>CFBundleName</key>",
            "\t<string>TestBundle</string>",
            "\t<key>CFBundlePackageType</key>",
            "\t<string>APPL</string>",
            "\t<key>CFBundleLocalizations</key>",
            "\t<array>",
            "\t\t<string>en</string>",
            "\t\t<string>es</string>",
            "\t</array>",
            "</dict>",
            "</plist>",
        ];
        file_put_contents($this->bundleRoot . DIRECTORY_SEPARATOR . "Info.plist", implode(PHP_EOL, $info));

        file_put_contents($this->resources . DIRECTORY_SEPARATOR . "data.json", "{\"a\":1}");
        file_put_contents($this->resources . DIRECTORY_SEPARATOR . "other.json", "{\"b\":2}");
        file_put_contents($this->resources . DIRECTORY_SEPARATOR . "notes.txt", "notes");
        file_put_contents($this->resources . DIRECTORY_SEPARATOR . "beep.mp3", "not really audio");
        // A valid 1x1 PNG and a file with a .png extension that is not a PNG at all.
        file_put_contents($this->resources . DIRECTORY_SEPARATOR . "logo.png", base64_decode("iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mNkYPhfDwAChwGA60e6kgAAAABJRU5ErkJggg=="));
        file_put_contents($this->resources . DIRECTORY_SEPARATOR . "bad.png", "this is not a png");
        file_put_contents($this->resources . DIRECTORY_SEPARATOR . "Extras" . DIRECTORY_SEPARATOR . "manual.txt", "manual");
        file_put_contents($this->resources . DIRECTORY_SEPARATOR . "es" . DIRECTORY_SEPARATOR . "greeting.txt", "hola");
        file_put_contents($this->bareRoot . DIRECTORY_SEPARATOR . "readme.md", "bare");

        $this->bundle = Bundle::bundleWithPath($this->bundleRoot);
        $this->bare = Bundle::bundleWithPath($this->bareRoot);
    }

    #[Override]
    protected function tearDown(): void
    {
        $paths = [
            $this->resources . DIRECTORY_SEPARATOR . "Extras" . DIRECTORY_SEPARATOR . "manual.txt",
            $this->resources . DIRECTORY_SEPARATOR . "es" . DIRECTORY_SEPARATOR . "greeting.txt",
            $this->resources . DIRECTORY_SEPARATOR . "data.json",
            $this->resources . DIRECTORY_SEPARATOR . "other.json",
            $this->resources . DIRECTORY_SEPARATOR . "notes.txt",
            $this->resources . DIRECTORY_SEPARATOR . "beep.mp3",
            $this->resources . DIRECTORY_SEPARATOR . "logo.png",
            $this->resources . DIRECTORY_SEPARATOR . "bad.png",
            $this->bundleRoot . DIRECTORY_SEPARATOR . "Info.plist",
            $this->bareRoot . DIRECTORY_SEPARATOR . "readme.md",
        ];
        foreach ($paths as $path) {
            @unlink($path);
        }
        @rmdir($this->resources . DIRECTORY_SEPARATOR . "Extras");
        @rmdir($this->resources . DIRECTORY_SEPARATOR . "es");
        @rmdir($this->resources);
        @rmdir($this->bundleRoot);
        @rmdir($this->bareRoot);
        @rmdir($this->root);
    }

    public function testIdentityAndCaching(): void
    {
        $this->assertSame($this->bundle, Bundle::bundleWithPath($this->bundleRoot), "bundleWithPath returns the cached instance for the same path");
        $this->assertSame($this->bundle, Bundle::bundleWithURL(URL::fileURL($this->bundleRoot)), "bundleWithURL shares the same cache");
        $this->assertInstanceOf(Dictionary::class, $this->bundle->infoDictionary, "infoDictionary is parsed from Info.plist");
        $this->assertSame("com.example.bundle-tests", $this->bundle->bundleIdentifier, "bundleIdentifier reads CFBundleIdentifier");
        $this->assertSame("TestBundle", $this->bundle->object(kCFBundleNameKey), "object() looks up Info.plist keys");
        $this->assertSame("en", $this->bundle->developmentLocalization, "developmentLocalization reads CFBundleDevelopmentRegion");
    }

    public function testDirectoryURLs(): void
    {
        $this->assertTrue($this->bundle->resourceURL !== null && $this->bundle->resourceURL->lastPathComponent === "Resources", "resourceURL finds the Resources directory");
        $this->assertNull($this->bundle->privateFrameworksURL, "privateFrameworksURL is null when the directory is missing");
        $this->assertNull($this->bundle->sharedFrameworksURL, "sharedFrameworksURL is null when the directory is missing");
        $this->assertNull($this->bundle->builtInPlugInsURL, "builtInPlugInsURL is null when the directory is missing");
        $this->assertNull($this->bundle->sharedSupportURL, "sharedSupportURL is null when the directory is missing");
        $this->assertNull($this->bundle->vendorURL, "vendorURL is null when the directory is missing");
        $this->assertNull($this->bundle->nodeModulesURL, "nodeModulesURL is null when the directory is missing");
        $this->assertNull($this->bundle->executableURL, "executableURL is null when the OS directory is missing");
    }

    public function testBareBundle(): void
    {
        $this->assertNull($this->bare->infoDictionary, "infoDictionary is null without an Info.plist");
        $this->assertNull($this->bare->executableURL, "executableURL is null without an Info.plist instead of raising TypeError");
        $this->assertNull($this->bare->resourceURL, "resourceURL is null without a Resources directory");
        $this->assertNotNull($this->bare->url("readme", "md"), "resource lookup falls back to the bundle root without a Resources directory");
    }

    public function testResourceLookupByName(): void
    {
        $this->assertSame("data.json", $this->bundle->url("data", "json")?->lastPathComponent, "url() finds a resource by name and extension");
        $this->assertNull($this->bundle->url("data", "txt"), "url() respects the requested extension");
        $this->assertNull($this->bundle->url("missing", "json"), "url() returns null for an unknown name");
        $this->assertSame("data.json", $this->bundle->url("data")?->lastPathComponent, "url() without an extension matches by name alone");
        $this->assertNotNull($this->bundle->url("manual", "txt", "Extras"), "url() searches the given subdirectory");
        $this->assertNull($this->bundle->url("manual", "txt"), "url() does not descend into subdirectories on its own");
        $this->assertSame("greeting.txt", $this->bundle->url("greeting", "txt", null, "es")?->lastPathComponent, "url() searches the given localization directory");
        $this->assertNull($this->bundle->url("greeting", "txt"), "localized resources are not found without the localization");
        $path = $this->bundle->path("data", "json");
        $this->assertTrue($path !== null && str_ends_with($path, "data.json"), "path() returns the pathname of the located resource");
    }

    public function testResourceEnumeration(): void
    {
        $jsonURLs = $this->bundle->urls("json");
        $this->assertInstanceOf(ArrayClass::class, $jsonURLs, "urls() returns a collection when resources match");
        $this->assertSame(2, $jsonURLs->count, "urls() returns every resource with the given extension");
        $jsonNames = $jsonURLs->map(fn(URL $url): string => $url->lastPathComponent);
        $this->assertTrue($jsonNames->containsElement("data.json") && $jsonNames->containsElement("other.json"), "urls() returns the expected files");
        $allNames = $this->bundle->urls()?->map(fn(URL $url): string => $url->lastPathComponent);
        $this->assertTrue($allNames !== null && $allNames->containsElement("notes.txt") && $allNames->containsElement("data.json"), "urls() without an extension returns everything in Resources");
        $this->assertNull($this->bundle->urls("json", "Extras"), "urls() returns null when nothing matches");
        $jsonPaths = $this->bundle->paths("json");
        $this->assertTrue($jsonPaths instanceof ArrayClass && $jsonPaths->allSatisfy(fn(string $p): bool => str_ends_with($p, ".json")), "paths() maps the matching URLs to pathnames");
    }

    public function testImagesAndSounds(): void
    {
        $this->assertSame("logo.png", $this->bundle->urlForImageResource("logo")?->lastPathComponent, "urlForImageResource finds the image by bare name");
        $imagePath = $this->bundle->pathForImageResource("logo");
        $this->assertTrue($imagePath !== null && str_ends_with($imagePath, "logo.png"), "pathForImageResource returns the pathname");
        $this->assertNull($this->bundle->urlForImageResource("notes"), "urlForImageResource ignores non-image resources");
        $soundPath = $this->bundle->pathForSoundResource("beep");
        $this->assertTrue($soundPath !== null && str_ends_with($soundPath, "beep.mp3"), "pathForSoundResource finds the sound file");
    }

    public function testImageDecoding(): void
    {
        if (!extension_loaded("gd")) {
            $this->markTestSkipped("the gd extension is not loaded");
        }
        $this->assertInstanceOf(GdImage::class, $this->bundle->image("logo"), "image() decodes a PNG resource");
        // imagecreatefrompng warns on garbage input; silence it so the null return path is observable.
        set_error_handler(fn(): bool => true);
        try {
            $this->assertNull($this->bundle->image("bad"), "image() returns null for an undecodable file instead of false");
        } finally {
            restore_error_handler();
        }
    }

    public function testLocalizations(): void
    {
        $this->assertTrue($this->bundle->localizations->count === 2 && $this->bundle->localizations->containsElement("en") && $this->bundle->localizations->containsElement("es"), "localizations reads CFBundleLocalizations");
        $preferred = $this->bundle->preferredLocalizations;
        $this->assertTrue($preferred->count === 2 && $preferred->containsElement("en") && $preferred->containsElement("es"), "preferredLocalizations reorders without dropping entries");
        $this->assertTrue($this->bare->localizations->isEmpty, "localizations is empty without an Info.plist");
    }

    public function testLocalizedStringUsesTheDocumentedFallbacks(): void
    {
        $key = "Missing bundle test localization";

        $this->assertSame($key, $this->bundle->localizedString($key));
        $this->assertSame("Fallback", $this->bundle->localizedString($key, "Fallback"));
        $this->assertSame("", $this->bundle->localizedString($key, ""));
        $this->assertSame("Fallback", $this->bare->localizedString($key, "Fallback"));
        $this->bundle->localizedString($key, null, "");
        $this->assertSame(realpath($this->resources), realpath($this->boundDirectory("Localizable")));
    }

    public function testLocalizedStringRebindsATableWhenSwitchingBundles(): void
    {
        $alternateRoot = $this->root . DIRECTORY_SEPARATOR . "AlternateBundle";
        $alternateResources = $alternateRoot . DIRECTORY_SEPARATOR . "Resources";
        mkdir($alternateResources, 0777, true);
        $alternate = Bundle::bundleWithPath($alternateRoot);
        $table = "BundleTest" . (int)getmypid();

        try {
            $this->bundle->localizedString("Missing", null, $table);
            $this->assertSame(realpath($this->resources), realpath($this->boundDirectory($table)));

            $alternate->localizedString("Missing", null, $table);
            $this->assertSame(realpath($alternateResources), realpath($this->boundDirectory($table)));

            $this->bundle->localizedString("Missing", null, $table);
            $this->assertSame(realpath($this->resources), realpath($this->boundDirectory($table)));
        } finally {
            rmdir($alternateResources);
            rmdir($alternateRoot);
        }
    }

    public function testClassLoadingAndBundleForClass(): void
    {
        $this->assertSame(URL::class, $this->bundle->classNamed(URL::class), "classNamed resolves an already-loaded class");
        $this->assertNull($this->bundle->classNamed("Definitely\\Missing\\ClassName"), "classNamed returns null for an unknown class");

        $foundation = Bundle::bundleForClass(Bundle::class);
        $this->assertSame("com.sabatiersoftware.foundation", $foundation->bundleIdentifier, "bundleForClass walks up to the framework root through src");
        // This test file lives in tests/, not under src, so the walk runs to the filesystem root; before the drive-root guard this looped forever on Windows.
        $this->assertInstanceOf(Bundle::class, Bundle::bundleForClass(self::class), "bundleForClass terminates for classes outside a src tree");
    }

    public function testBundleRegistries(): void
    {
        $foundation = Bundle::bundleForClass(Bundle::class);
        $this->assertTrue(Bundle::allBundles()->containsElement($this->bundle), "allBundles includes non-framework bundles");
        $this->assertFalse(Bundle::allBundles()->containsElement($foundation), "allBundles excludes frameworks");
        $this->assertTrue(Bundle::allFrameworks()->containsElement($foundation), "allFrameworks includes FMWK bundles");
        $this->assertFalse(Bundle::allFrameworks()->containsElement($this->bundle), "allFrameworks excludes applications");
        $this->assertSame($this->bundle, Bundle::bundleWithIdentifier("COM.EXAMPLE.BUNDLE-TESTS"), "bundleWithIdentifier matches case-insensitively");
        $this->assertNull(Bundle::bundleWithIdentifier("com.example.nope"), "bundleWithIdentifier returns null for unknown identifiers");
    }

    /**
     * Psalm requires the optional directory argument when querying an existing gettext binding.
     * @noinspection PhpSameParameterValueInspection
     */
    private function boundDirectory(string $table, ?string $directory = null): string
    {
        return bindtextdomain($table, $directory);
    }
}
