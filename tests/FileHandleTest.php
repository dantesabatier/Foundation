<?php

declare(strict_types=1);

/**
 * Standalone tests for src/FileHandle.php.
 *
 * Run with: php tests/FileHandleTest.php
 * Exits with a non-zero status code if any check fails.
 *
 * Regression guards:
 *  - the reading factory used to throw instead of returning null for a missing file
 *    (unsafe_value turned the fopen() warning into an exception before the null check);
 *  - close() used to double-fclose when called explicitly (the destructor closes again),
 *    producing a fatal TypeError at shutdown;
 *  - seekToEnd() used fseek(-1, SEEK_END) and returned size - 1 instead of the file size
 *    its contract promises;
 *  - offsetInFile is a hooked property (was the offset() method).
 */

namespace Sabatier\Foundation\Tests;

use Sabatier\Foundation\FileHandle;
use Sabatier\Foundation\URL;

require __DIR__ . "/../vendor/autoload.php";

final class FileHandleTestRunner
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

/** Fails the process on any PHP warning/notice, so a resurfaced double-fclose is caught. */
set_error_handler(function (int $severity, string $message, string $file, int $line): bool {
    throw new \ErrorException($message, 0, $severity, $file, $line);
});

$check = FileHandleTestRunner::check(...);
$section = FileHandleTestRunner::section(...);

$directory = sys_get_temp_dir() . DIRECTORY_SEPARATOR . "sabatier-filehandle-test-" . getmypid();
mkdir($directory);
$existing = $directory . DIRECTORY_SEPARATOR . "existing.txt";
file_put_contents($existing, "hello world");

try {
    // -----------------------------------------------------------------------
    $section("factories");
    // -----------------------------------------------------------------------

    // A missing file must produce null, not an exception escaping the factory.
    $check(FileHandle::fileHandleForReadingFromURL(URL::fileURL($directory . DIRECTORY_SEPARATOR . "missing.txt")) === null, "reading factory returns null for a missing file");
    $check(FileHandle::fileHandleForReadingFromURL(URL::fileURL($existing)) instanceof FileHandle, "reading factory opens an existing file");
    $check(FileHandle::standardOutput() === FileHandle::standardOutput(), "standardOutput is a shared instance");

    // -----------------------------------------------------------------------
    $section("reading");
    // -----------------------------------------------------------------------

    $reader = FileHandle::fileHandleForReadingFromURL(URL::fileURL($existing));
    $check($reader->offsetInFile === 0, "offsetInFile starts at 0");
    $check($reader->read(5) === "hello", "read returns the requested bytes");
    $check($reader->offsetInFile === 5, "read advances offsetInFile");
    $check($reader->readToEnd() === " world", "readToEnd reads from the current offset");
    $reader->seek(6);
    $check($reader->offsetInFile === 6, "seek moves the file pointer");
    $check($reader->availableData === "world", "availableData reads from the current offset to the end");
    $check($reader->seekToEnd() === 11, "seekToEnd returns the file size");
    $reader->close();

    // -----------------------------------------------------------------------
    $section("closing");
    // -----------------------------------------------------------------------

    $closable = FileHandle::fileHandleForReadingFromURL(URL::fileURL($existing));
    $closable->close();
    try {
        $closable->close();
        $check(true, "close is idempotent");
    } catch (\Throwable) {
        $check(false, "close is idempotent");
    }
    // The destructor also calls close(); with the handle already closed it must not fail.
    unset($closable);
    $check(true, "destruction after an explicit close does not fail");

    // -----------------------------------------------------------------------
    $section("writing");
    // -----------------------------------------------------------------------

    $written = $directory . DIRECTORY_SEPARATOR . "written.txt";
    file_put_contents($written, "");
    $writer = FileHandle::fileHandleForWritingToURL(URL::fileURL($written));
    $check($writer instanceof FileHandle, "writing factory opens the file");
    $writer->write("data");
    $writer->synchronize();
    $check(file_get_contents($written) === "data", "write persists the data");
    $writer->truncate(2);
    $writer->close();
    $check(file_get_contents($written) === "da", "truncate shortens the file");

    $updated = $directory . DIRECTORY_SEPARATOR . "updated.txt";
    file_put_contents($updated, "old");
    $updater = FileHandle::fileHandleForUpdatingURL(URL::fileURL($updated));
    $updater->write("new content");
    $updater->seek(0);
    $check($updater->read(3) === "new", "updating handle can write and read back");
    $updater->close();
} finally {
    @unlink($existing);
    @unlink($directory . DIRECTORY_SEPARATOR . "written.txt");
    @unlink($directory . DIRECTORY_SEPARATOR . "updated.txt");
    @rmdir($directory);
}

FileHandleTestRunner::finish();
