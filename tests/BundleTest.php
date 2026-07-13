<?php

declare(strict_types=1);

/**
 * Standalone tests for src/Bundle.php.
 *
 * Run with: php tests/BundleTest.php
 * Exits with a non-zero status code if any check fails.
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

namespace Sabatier\Foundation\Tests;

use GdImage;
use Sabatier\Foundation\ArrayClass;
use Sabatier\Foundation\Bundle;
use Sabatier\Foundation\Dictionary;
use Sabatier\Foundation\URL;

use const Sabatier\Foundation\kCFBundleNameKey;

require __DIR__ . "/../vendor/autoload.php";

final class BundleTestRunner
{
    public static int $passed = 0;
    /** @var list<string> */
    public static array $failures = [];
    private static string $section = "";

    public static function section(string $name): void
    {
        self::$section = $name;
    }

    public static function check(bool $condition, string $message): void
    {
        if ($condition) {
            self::$passed++;
            return;
        }
        $failure = self::$section === "" ? $message : self::$section . ": " . $message;
        self::$failures[] = $failure;
        fwrite(STDERR, "FAIL $failure" . PHP_EOL);
    }

    public static function finish(): never
    {
        $failed = count(self::$failures);
        printf("%d passed, %d failed%s", self::$passed, $failed, PHP_EOL);
        exit($failed > 0 ? 1 : 0);
    }
}

/** Fails the process on any PHP warning/notice, honoring the @ suppression operator. */
set_error_handler(function (int $severity, string $message, string $file, int $line): bool {
    if (!(error_reporting() & $severity)) {
        return false;
    }
    throw new \ErrorException($message, 0, $severity, $file, $line);
});

$check = BundleTestRunner::check(...);
$section = BundleTestRunner::section(...);

// ---------------------------------------------------------------------------
// Fixture: a bundle directory with an Info.plist and a Resources tree, plus a
// bare directory with neither.
// ---------------------------------------------------------------------------

$root = sys_get_temp_dir() . DIRECTORY_SEPARATOR . "sabatier-bundle-test-" . getmypid();
$bundleRoot = $root . DIRECTORY_SEPARATOR . "TestBundle";
$bareRoot = $root . DIRECTORY_SEPARATOR . "BareBundle";
$resources = $bundleRoot . DIRECTORY_SEPARATOR . "Resources";
mkdir($resources . DIRECTORY_SEPARATOR . "Extras", 0777, true);
mkdir($resources . DIRECTORY_SEPARATOR . "es", 0777, true);
mkdir($bareRoot, 0777, true);

file_put_contents($bundleRoot . DIRECTORY_SEPARATOR . "Info.plist", <<<PLIST
<?xml version="1.0" encoding="UTF-8"?>
<!DOCTYPE plist PUBLIC "-//Apple//DTD PLIST 1.0//EN" "http://www.apple.com/DTDs/PropertyList-1.0.dtd">
<plist>
<dict>
	<key>CFBundleDevelopmentRegion</key>
	<string>en</string>
	<key>CFBundleIdentifier</key>
	<string>com.example.bundle-tests</string>
	<key>CFBundleName</key>
	<string>TestBundle</string>
	<key>CFBundlePackageType</key>
	<string>APPL</string>
	<key>CFBundleLocalizations</key>
	<array>
		<string>en</string>
		<string>es</string>
	</array>
</dict>
</plist>
PLIST);

file_put_contents($resources . DIRECTORY_SEPARATOR . "data.json", "{\"a\":1}");
file_put_contents($resources . DIRECTORY_SEPARATOR . "other.json", "{\"b\":2}");
file_put_contents($resources . DIRECTORY_SEPARATOR . "notes.txt", "notes");
file_put_contents($resources . DIRECTORY_SEPARATOR . "beep.mp3", "not really audio");
// A valid 1x1 PNG and a file with a .png extension that is not a PNG at all.
file_put_contents($resources . DIRECTORY_SEPARATOR . "logo.png", base64_decode("iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mNkYPhfDwAChwGA60e6kgAAAABJRU5ErkJggg=="));
file_put_contents($resources . DIRECTORY_SEPARATOR . "bad.png", "this is not a png");
file_put_contents($resources . DIRECTORY_SEPARATOR . "Extras" . DIRECTORY_SEPARATOR . "manual.txt", "manual");
file_put_contents($resources . DIRECTORY_SEPARATOR . "es" . DIRECTORY_SEPARATOR . "greeting.txt", "hola");
file_put_contents($bareRoot . DIRECTORY_SEPARATOR . "readme.md", "bare");

try {
    // -----------------------------------------------------------------------
    $section("identity and caching");
    // -----------------------------------------------------------------------

    $bundle = Bundle::bundleWithPath($bundleRoot);
    $check(Bundle::bundleWithPath($bundleRoot) === $bundle, "bundleWithPath returns the cached instance for the same path");
    $check(Bundle::bundleWithURL(URL::fileURL($bundleRoot)) === $bundle, "bundleWithURL shares the same cache");
    $check($bundle->infoDictionary instanceof Dictionary, "infoDictionary is parsed from Info.plist");
    $check($bundle->bundleIdentifier === "com.example.bundle-tests", "bundleIdentifier reads CFBundleIdentifier");
    $check($bundle->object(kCFBundleNameKey) === "TestBundle", "object() looks up Info.plist keys");
    $check($bundle->developmentLocalization === "en", "developmentLocalization reads CFBundleDevelopmentRegion");

    // -----------------------------------------------------------------------
    $section("directory URLs");
    // -----------------------------------------------------------------------

    $check($bundle->resourceURL !== null && $bundle->resourceURL->lastPathComponent === "Resources", "resourceURL finds the Resources directory");
    $check($bundle->privateFrameworksURL === null, "privateFrameworksURL is null when the directory is missing");
    $check($bundle->sharedFrameworksURL === null, "sharedFrameworksURL is null when the directory is missing");
    $check($bundle->builtInPlugInsURL === null, "builtInPlugInsURL is null when the directory is missing");
    $check($bundle->sharedSupportURL === null, "sharedSupportURL is null when the directory is missing");
    $check($bundle->vendorURL === null, "vendorURL is null when the directory is missing");
    $check($bundle->nodeModulesURL === null, "nodeModulesURL is null when the directory is missing");
    $check($bundle->executableURL === null, "executableURL is null when the OS directory is missing");

    $bare = Bundle::bundleWithPath($bareRoot);
    $check($bare->infoDictionary === null, "infoDictionary is null without an Info.plist");
    $check($bare->executableURL === null, "executableURL is null without an Info.plist instead of raising TypeError");
    $check($bare->resourceURL === null, "resourceURL is null without a Resources directory");
    $check($bare->url("readme", "md") !== null, "resource lookup falls back to the bundle root without a Resources directory");

    // -----------------------------------------------------------------------
    $section("resource lookup by name");
    // -----------------------------------------------------------------------

    $check($bundle->url("data", "json")?->lastPathComponent === "data.json", "url() finds a resource by name and extension");
    $check($bundle->url("data", "txt") === null, "url() respects the requested extension");
    $check($bundle->url("missing", "json") === null, "url() returns null for an unknown name");
    $check($bundle->url("data")?->lastPathComponent === "data.json", "url() without an extension matches by name alone");
    $check($bundle->url("manual", "txt", "Extras") !== null, "url() searches the given subdirectory");
    $check($bundle->url("manual", "txt") === null, "url() does not descend into subdirectories on its own");
    $check($bundle->url("greeting", "txt", null, "es")?->lastPathComponent === "greeting.txt", "url() searches the given localization directory");
    $check($bundle->url("greeting", "txt") === null, "localized resources are not found without the localization");
    $path = $bundle->path("data", "json");
    $check($path !== null && str_ends_with($path, "data.json"), "path() returns the pathname of the located resource");

    // -----------------------------------------------------------------------
    $section("resource enumeration");
    // -----------------------------------------------------------------------

    $jsonURLs = $bundle->urls("json");
    $check($jsonURLs instanceof ArrayClass && $jsonURLs->count === 2, "urls() returns every resource with the given extension");
    $jsonNames = $jsonURLs?->map(fn(URL $url): string => $url->lastPathComponent);
    $check($jsonNames !== null && $jsonNames->containsElement("data.json") && $jsonNames->containsElement("other.json"), "urls() returns the expected files");
    $allNames = $bundle->urls()?->map(fn(URL $url): string => $url->lastPathComponent);
    $check($allNames !== null && $allNames->containsElement("notes.txt") && $allNames->containsElement("data.json"), "urls() without an extension returns everything in Resources");
    $check($bundle->urls("json", "Extras") === null, "urls() returns null when nothing matches");
    $jsonPaths = $bundle->paths("json");
    $check($jsonPaths instanceof ArrayClass && $jsonPaths->allSatisfy(fn(string $p): bool => str_ends_with($p, ".json")), "paths() maps the matching URLs to pathnames");

    // -----------------------------------------------------------------------
    $section("images and sounds");
    // -----------------------------------------------------------------------

    $check($bundle->urlForImageResource("logo")?->lastPathComponent === "logo.png", "urlForImageResource finds the image by bare name");
    $imagePath = $bundle->pathForImageResource("logo");
    $check($imagePath !== null && str_ends_with($imagePath, "logo.png"), "pathForImageResource returns the pathname");
    $check($bundle->urlForImageResource("notes") === null, "urlForImageResource ignores non-image resources");
    $soundPath = $bundle->pathForSoundResource("beep");
    $check($soundPath !== null && str_ends_with($soundPath, "beep.mp3"), "pathForSoundResource finds the sound file");
    if (extension_loaded("gd")) {
        $check($bundle->image("logo") instanceof GdImage, "image() decodes a PNG resource");
        // imagecreatefrompng warns on garbage input; silence it so the null return path is observable.
        set_error_handler(fn(): bool => true);
        try {
            $check($bundle->image("bad") === null, "image() returns null for an undecodable file instead of false");
        } finally {
            restore_error_handler();
        }
    }

    // -----------------------------------------------------------------------
    $section("localizations");
    // -----------------------------------------------------------------------

    $check($bundle->localizations->count === 2 && $bundle->localizations->containsElement("en") && $bundle->localizations->containsElement("es"), "localizations reads CFBundleLocalizations");
    $preferred = $bundle->preferredLocalizations;
    $check($preferred->count === 2 && $preferred->containsElement("en") && $preferred->containsElement("es"), "preferredLocalizations reorders without dropping entries");
    $check($bare->localizations->isEmpty, "localizations is empty without an Info.plist");

    // -----------------------------------------------------------------------
    $section("class loading and bundleForClass");
    // -----------------------------------------------------------------------

    $check($bundle->classNamed(URL::class) === URL::class, "classNamed resolves an already-loaded class");
    $check($bundle->classNamed("Definitely\\Missing\\ClassName") === null, "classNamed returns null for an unknown class");

    $foundation = Bundle::bundleForClass(Bundle::class);
    $check($foundation->bundleIdentifier === "com.sabatiersoftware.foundation", "bundleForClass walks up to the framework root through src");
    // This test file lives in tests/, not under src, so the walk runs to the
    // filesystem root; before the drive-root guard this looped forever on Windows.
    $check(Bundle::bundleForClass(BundleTestRunner::class) instanceof Bundle, "bundleForClass terminates for classes outside a src tree");

    // -----------------------------------------------------------------------
    $section("bundle registries");
    // -----------------------------------------------------------------------

    $check(Bundle::allBundles()->containsElement($bundle), "allBundles includes non-framework bundles");
    $check(!Bundle::allBundles()->containsElement($foundation), "allBundles excludes frameworks");
    $check(Bundle::allFrameworks()->containsElement($foundation), "allFrameworks includes FMWK bundles");
    $check(!Bundle::allFrameworks()->containsElement($bundle), "allFrameworks excludes applications");
    $check(Bundle::bundleWithIdentifier("COM.EXAMPLE.BUNDLE-TESTS") === $bundle, "bundleWithIdentifier matches case-insensitively");
    $check(Bundle::bundleWithIdentifier("com.example.nope") === null, "bundleWithIdentifier returns null for unknown identifiers");
} finally {
    $paths = [
        $resources . DIRECTORY_SEPARATOR . "Extras" . DIRECTORY_SEPARATOR . "manual.txt",
        $resources . DIRECTORY_SEPARATOR . "es" . DIRECTORY_SEPARATOR . "greeting.txt",
        $resources . DIRECTORY_SEPARATOR . "data.json",
        $resources . DIRECTORY_SEPARATOR . "other.json",
        $resources . DIRECTORY_SEPARATOR . "notes.txt",
        $resources . DIRECTORY_SEPARATOR . "beep.mp3",
        $resources . DIRECTORY_SEPARATOR . "logo.png",
        $resources . DIRECTORY_SEPARATOR . "bad.png",
        $bundleRoot . DIRECTORY_SEPARATOR . "Info.plist",
        $bareRoot . DIRECTORY_SEPARATOR . "readme.md",
    ];
    foreach ($paths as $path) {
        @unlink($path);
    }
    @rmdir($resources . DIRECTORY_SEPARATOR . "Extras");
    @rmdir($resources . DIRECTORY_SEPARATOR . "es");
    @rmdir($resources);
    @rmdir($bundleRoot);
    @rmdir($bareRoot);
    @rmdir($root);
}

BundleTestRunner::finish();
