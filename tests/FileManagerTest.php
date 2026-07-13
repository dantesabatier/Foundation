<?php

declare(strict_types=1);

/**
 * Standalone tests for src/FileManager.php and the directory enumeration built on URL.
 *
 * Run with: php tests/FileManagerTest.php
 * Exits with a non-zero status code if any check fails.
 *
 * Regression guards:
 *  - URLDirectoryEnumerator receives SplFileInfo objects from its iterator and must cast
 *    them before handing them to URL::fileURL() (a TypeError under strict_types);
 *  - hasDirectoryPath / fileSystemRepresentation must work with Windows drive paths
 *    ("/C:/..." vs "C:/...").
 */

namespace Sabatier\Foundation\Tests;

use Sabatier\Foundation\FileManager;
use Sabatier\Foundation\URL;

require __DIR__ . "/../vendor/autoload.php";

final class FileManagerTestRunner
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

$check = FileManagerTestRunner::check(...);
$section = FileManagerTestRunner::section(...);

$manager = FileManager::default();
$root = sys_get_temp_dir() . DIRECTORY_SEPARATOR . "sabatier-filemanager-test-" . getmypid();
mkdir($root . DIRECTORY_SEPARATOR . "sub", 0777, true);
file_put_contents($root . DIRECTORY_SEPARATOR . "a.txt", "alpha");
file_put_contents($root . DIRECTORY_SEPARATOR . "sub" . DIRECTORY_SEPARATOR . "b.txt", "beta");
$rootURL = URL::fileURL($root);

try {
    // -----------------------------------------------------------------------
    $section("existence checks");
    // -----------------------------------------------------------------------

    $check($manager->fileExists($root . DIRECTORY_SEPARATOR . "a.txt") === true, "fileExists on a file");
    $check($manager->fileExists($root, $isDirectory) === true && $isDirectory === true, "fileExists reports a directory through its by-ref flag");
    $manager->fileExists($root . DIRECTORY_SEPARATOR . "a.txt", $isDirectory);
    $check($isDirectory === false, "the by-ref flag is false for a regular file");
    $check($manager->fileExists($root . DIRECTORY_SEPARATOR . "missing.bin") === false, "fileExists on a missing path");
    $check($manager->isReadableFile($root . DIRECTORY_SEPARATOR . "a.txt") === true, "isReadableFile");
    $check($manager->contents($root . DIRECTORY_SEPARATOR . "a.txt") === "alpha", "contents reads the file");

    // -----------------------------------------------------------------------
    $section("directory listing");
    // -----------------------------------------------------------------------

    $listing = $manager->contentsOfDirectory($rootURL);
    $check($listing->count === 2, "contentsOfDirectory lists direct children only");
    $names = $listing->map(fn(URL $url): string => $url->lastPathComponent)->sort();
    $check($names->array === ["a.txt", "sub"], "contentsOfDirectory returns the child names");
    $check($listing->allSatisfy(fn(URL $url): bool => $url->isFileURL), "contentsOfDirectory returns file URLs");
    $check($listing->allSatisfy(fn(URL $url): bool => $url->deletingLastPathComponent()->isEqual($rootURL->standardized)), "every child resolves back to its parent");

    // The deep enumerator drives URLDirectoryEnumerator, which used to pass SplFileInfo
    // straight into URL::fileURL().
    $enumerated = [];
    foreach ($manager->enumerator($rootURL) as $url) {
        $enumerated[] = $url->lastPathComponent;
    }
    sort($enumerated);
    $check($enumerated === ["a.txt", "b.txt", "sub"], "deep enumeration yields every descendant");

    // -----------------------------------------------------------------------
    $section("creation, copy, move, removal");
    // -----------------------------------------------------------------------

    $created = $rootURL->appendingPathComponent("nested")->appendingPathComponent("deep");
    $check($manager->createDirectory($created, true) === true, "createDirectory with intermediates");
    $check($manager->fileExists($created->fileSystemRepresentation, $isDirectory) && $isDirectory, "created directory exists");

    $check($manager->createFile($root . DIRECTORY_SEPARATOR . "c.txt", "gamma") === true, "createFile");
    $check($manager->contents($root . DIRECTORY_SEPARATOR . "c.txt") === "gamma", "createFile writes the data");

    $sourceURL = $rootURL->appendingPathComponent("c.txt");
    $copyURL = $rootURL->appendingPathComponent("c-copy.txt");
    $check($manager->copyItem($sourceURL, $copyURL) === true, "copyItem");
    $check($manager->contents($root . DIRECTORY_SEPARATOR . "c-copy.txt") === "gamma", "copy has the same contents");
    $check($manager->fileExists($root . DIRECTORY_SEPARATOR . "c.txt") === true, "copyItem keeps the source");

    $movedURL = $rootURL->appendingPathComponent("c-moved.txt");
    $check($manager->moveItem($copyURL, $movedURL) === true, "moveItem");
    $check($manager->fileExists($root . DIRECTORY_SEPARATOR . "c-moved.txt") === true, "moved file exists");
    $check($manager->fileExists($root . DIRECTORY_SEPARATOR . "c-copy.txt") === false, "moveItem removes the source");

    $check($manager->removeItem($movedURL) === true, "removeItem on a file");
    $check($manager->fileExists($root . DIRECTORY_SEPARATOR . "c-moved.txt") === false, "removed file no longer exists");
    $check($manager->removeItem($sourceURL) === true, "removeItem cleans up");

    // -----------------------------------------------------------------------
    $section("URL filesystem semantics");
    // -----------------------------------------------------------------------

    $check($rootURL->hasDirectoryPath === true, "hasDirectoryPath consults the filesystem for an existing directory");
    $check($rootURL->appendingPathComponent("a.txt")->hasDirectoryPath === false, "hasDirectoryPath is false for an existing file");
    $expected = realpath($root . DIRECTORY_SEPARATOR . "a.txt");
    $check($expected !== false && $rootURL->appendingPathComponent("a.txt")->fileSystemRepresentation === $expected, "fileSystemRepresentation matches realpath");
} finally {
    @unlink($root . DIRECTORY_SEPARATOR . "a.txt");
    @unlink($root . DIRECTORY_SEPARATOR . "c.txt");
    @unlink($root . DIRECTORY_SEPARATOR . "c-copy.txt");
    @unlink($root . DIRECTORY_SEPARATOR . "c-moved.txt");
    @unlink($root . DIRECTORY_SEPARATOR . "sub" . DIRECTORY_SEPARATOR . "b.txt");
    @rmdir($root . DIRECTORY_SEPARATOR . "sub");
    @rmdir($root . DIRECTORY_SEPARATOR . "nested" . DIRECTORY_SEPARATOR . "deep");
    @rmdir($root . DIRECTORY_SEPARATOR . "nested");
    @rmdir($root);
}

FileManagerTestRunner::finish();
