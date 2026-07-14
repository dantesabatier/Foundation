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
 *    ("/C:/..." vs "C:/...");
 *  - removeItem must delete symbolic links instead of silently reporting success;
 *  - createSymbolicLink must create the link at $sourceURL pointing to $destinationURL
 *    (the arguments used to be fed to symlink() in the wrong order);
 *  - copyItem must copy directories recursively and refuse an existing destination;
 *  - isDeletableFile must check the parent directory's writability, not the file's;
 *  - trashItem must keep digits in colliding names and not add a trailing dot to
 *    extensionless files;
 *  - attributesOfItem must expose posixPermissions without filetype bits.
 */

namespace Sabatier\Foundation\Tests;

use Sabatier\Foundation\FileAttributeKey;
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
$originalPWD = $_SERVER["PWD"] ?? null;

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
    $check($manager->createDirectory($created, true) === true, "createDirectory with intermediates succeeds when the directory already exists");

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
    $section("directory copy");
    // -----------------------------------------------------------------------

    $treeURL = $rootURL->appendingPathComponent("tree");
    $manager->createDirectory($treeURL->appendingPathComponent("inner"), true);
    $manager->createFile($root . DIRECTORY_SEPARATOR . "tree" . DIRECTORY_SEPARATOR . "one.txt", "uno");
    $manager->createFile($root . DIRECTORY_SEPARATOR . "tree" . DIRECTORY_SEPARATOR . ".hidden", "oculto");
    $manager->createFile($root . DIRECTORY_SEPARATOR . "tree" . DIRECTORY_SEPARATOR . "inner" . DIRECTORY_SEPARATOR . "two.txt", "dos");

    $treeCopyURL = $rootURL->appendingPathComponent("tree-copy");
    $check($manager->copyItem($treeURL, $treeCopyURL) === true, "copyItem copies a directory");
    $treeCopyPath = $root . DIRECTORY_SEPARATOR . "tree-copy";
    $check($manager->contents($treeCopyPath . DIRECTORY_SEPARATOR . "one.txt") === "uno", "the copy contains the direct children");
    $check($manager->contents($treeCopyPath . DIRECTORY_SEPARATOR . "inner" . DIRECTORY_SEPARATOR . "two.txt") === "dos", "copyItem descends into subdirectories");
    $check($manager->fileExists($treeCopyPath . DIRECTORY_SEPARATOR . ".hidden") === true, "copyItem includes hidden files");
    $check($manager->contents($root . DIRECTORY_SEPARATOR . "tree" . DIRECTORY_SEPARATOR . "one.txt") === "uno", "copyItem keeps the source tree");

    $threw = false;
    try {
        $manager->copyItem($treeURL, $treeCopyURL);
    } catch (\Throwable) {
        $threw = true;
    }
    $check($threw, "copyItem throws when the destination already exists");

    $check($manager->removeItem($treeCopyURL) === true, "removeItem on a directory tree");
    $check($manager->fileExists($treeCopyPath) === false, "the removed tree no longer exists");

    // -----------------------------------------------------------------------
    $section("symbolic links");
    // -----------------------------------------------------------------------

    $linkPath = $root . DIRECTORY_SEPARATOR . "a-link.txt";
    $linkURL = $rootURL->appendingPathComponent("a-link.txt");
    $linkTargetURL = $rootURL->appendingPathComponent("a.txt");
    $linkSupported = true;
    try {
        $manager->createSymbolicLink($linkURL, $linkTargetURL);
    } catch (\Throwable) {
        // Creating symlinks on Windows requires Developer Mode or elevation.
        $linkSupported = false;
        print "NOTE symbolic links are not supported in this environment; skipping the section" . PHP_EOL;
    }
    if ($linkSupported) {
        $check(is_link($linkPath), "createSymbolicLink creates the link at the source URL");
        $check($manager->contents($linkPath) === "alpha", "the link points at the destination URL");
        $check($manager->destinationOfSymbolicLink($linkPath) === realpath($root . DIRECTORY_SEPARATOR . "a.txt"), "destinationOfSymbolicLink reads the link back");
        $check($manager->removeItem($linkURL) === true, "removeItem on a symlink");
        $check(is_link($linkPath) === false, "removeItem deletes the link itself");
        $check($manager->fileExists($root . DIRECTORY_SEPARATOR . "a.txt") === true, "removeItem keeps the link target");
    }

    // -----------------------------------------------------------------------
    $section("deletability");
    // -----------------------------------------------------------------------

    $check($manager->isDeletableFile($root . DIRECTORY_SEPARATOR . "a.txt") === true, "isDeletableFile is true for a file in a writable directory");
    $check($manager->isDeletableFile($root . DIRECTORY_SEPARATOR . "missing.bin") === false, "isDeletableFile is false for a missing file");
    if (PHP_OS_FAMILY !== "Windows") {
        // POSIX only: Windows ignores the write bit on directories.
        $lockedPath = $root . DIRECTORY_SEPARATOR . "locked";
        mkdir($lockedPath);
        file_put_contents($lockedPath . DIRECTORY_SEPARATOR . "captive.txt", "x");
        chmod($lockedPath . DIRECTORY_SEPARATOR . "captive.txt", 0666);
        chmod($lockedPath, 0555);
        $check($manager->isDeletableFile($lockedPath . DIRECTORY_SEPARATOR . "captive.txt") === false, "isDeletableFile is false for a writable file in a read-only directory");
        chmod($lockedPath, 0755);
    }

    // -----------------------------------------------------------------------
    $section("attributes");
    // -----------------------------------------------------------------------

    $attributes = $manager->attributesOfItem($root . DIRECTORY_SEPARATOR . "a.txt");
    $permissions = $attributes[FileAttributeKey::posixPermissions];
    $check(is_int($permissions) && ($permissions & ~0o7777) === 0, "posixPermissions carries permission bits only, no filetype bits");
    $check($attributes[FileAttributeKey::size] === 5, "the size attribute matches the contents");

    // -----------------------------------------------------------------------
    $section("trash");
    // -----------------------------------------------------------------------

    // documentRootDirectory honors $_SERVER["PWD"] on the CLI and is lazy per instance,
    // so a fresh manager confines the Trash directory to the sandbox.
    $_SERVER["PWD"] = $root;
    $trashManager = new FileManager();
    $trashPath = $root . DIRECTORY_SEPARATOR . "Trash";

    $victimPath = $root . DIRECTORY_SEPARATOR . "photo2024.txt";
    $victimURL = $rootURL->appendingPathComponent("photo2024.txt");
    $trashManager->createFile($victimPath, "first");
    $check($trashManager->trashItem($victimURL, $trashedURL) === true, "trashItem moves the item to the trash");
    $check($trashedURL->lastPathComponent === "photo2024.txt", "trashItem keeps the original name when it is free");
    $check($trashManager->contents($trashPath . DIRECTORY_SEPARATOR . "photo2024.txt") === "first", "the trashed item lives in the Trash directory");
    $check($trashManager->fileExists($victimPath) === false, "trashItem removes the original");

    $trashManager->createFile($victimPath, "second");
    $check($trashManager->trashItem($victimURL, $renamedURL) === true, "trashItem resolves a name collision");
    $check($renamedURL->lastPathComponent === "photo2024 1.txt", "the collision name keeps the digits of the original");
    $check($trashManager->contents($renamedURL->path) === "second", "the renamed trashed item keeps its contents");

    $plainPath = $root . DIRECTORY_SEPARATOR . "README";
    $plainURL = $rootURL->appendingPathComponent("README");
    $trashManager->createFile($plainPath, "read me");
    $check($trashManager->trashItem($plainURL, $plainTrashedURL) === true, "trashItem accepts an extensionless item");
    $check($plainTrashedURL->lastPathComponent === "README", "no trailing dot is added to an extensionless name");
    $trashManager->createFile($plainPath, "read me again");
    $check($trashManager->trashItem($plainURL, $plainRenamedURL) === true, "trashItem resolves an extensionless collision");
    $check($plainRenamedURL->lastPathComponent === "README 1", "the extensionless collision name has no dot either");

    // -----------------------------------------------------------------------
    $section("URL filesystem semantics");
    // -----------------------------------------------------------------------

    $check($rootURL->hasDirectoryPath === true, "hasDirectoryPath consults the filesystem for an existing directory");
    $check($rootURL->appendingPathComponent("a.txt")->hasDirectoryPath === false, "hasDirectoryPath is false for an existing file");
    $expected = realpath($root . DIRECTORY_SEPARATOR . "a.txt");
    $check($expected !== false && $rootURL->appendingPathComponent("a.txt")->fileSystemRepresentation === $expected, "fileSystemRepresentation matches realpath");
} finally {
    if ($originalPWD === null) {
        unset($_SERVER["PWD"]);
    } else {
        $_SERVER["PWD"] = $originalPWD;
    }
    $wipe = function (string $path) use (&$wipe): void {
        if (is_link($path)) {
            @unlink($path) || @rmdir($path);
            return;
        }
        if (is_dir($path)) {
            @chmod($path, 0755);
            foreach (scandir($path) ?: [] as $entry) {
                if ($entry !== "." && $entry !== "..") {
                    $wipe($path . DIRECTORY_SEPARATOR . $entry);
                }
            }
            @rmdir($path);
            return;
        }
        @unlink($path);
    };
    $wipe($root);
}

FileManagerTestRunner::finish();
