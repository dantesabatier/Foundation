<?php

declare(strict_types=1);

namespace Sabatier\Foundation\Tests;

use PHPUnit\Framework\TestCase;
use Sabatier\Foundation\FileHandle;
use Sabatier\Foundation\URL;

/**
 * Tests for src/FileHandle.php.
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
final class FileHandleTest extends TestCase
{
    private string $directory;
    private string $existing;

    protected function setUp(): void
    {
        $this->directory = sys_get_temp_dir() . DIRECTORY_SEPARATOR . "sabatier-filehandle-test-" . getmypid();
        if (!is_dir($this->directory)) {
            mkdir($this->directory);
        }
        $this->existing = $this->directory . DIRECTORY_SEPARATOR . "existing.txt";
        file_put_contents($this->existing, "hello world");
    }

    protected function tearDown(): void
    {
        @unlink($this->existing);
        @unlink($this->directory . DIRECTORY_SEPARATOR . "written.txt");
        @unlink($this->directory . DIRECTORY_SEPARATOR . "updated.txt");
        @rmdir($this->directory);
    }

    public function testFactories(): void
    {
        // A missing file must produce null, not an exception escaping the factory.
        $this->assertNull(FileHandle::fileHandleForReadingFromURL(URL::fileURL($this->directory . DIRECTORY_SEPARATOR . "missing.txt")), "reading factory returns null for a missing file");
        $this->assertInstanceOf(FileHandle::class, FileHandle::fileHandleForReadingFromURL(URL::fileURL($this->existing)), "reading factory opens an existing file");
        $this->assertSame(FileHandle::standardOutput(), FileHandle::standardOutput(), "standardOutput is a shared instance");
    }

    public function testInMemoryHandlesAreIndependent(): void
    {
        $first = FileHandle::inMemory();
        $second = FileHandle::inMemory();

        $first->write("first");
        $second->write("second");
        $first->seek(0);
        $second->seek(0);

        $this->assertNotSame($first, $second, "each factory call returns a new handle");
        $this->assertSame("first", $first->readToEnd(), "the first handle retains its own contents");
        $this->assertSame("second", $second->readToEnd(), "the second handle retains its own contents");

        $first->close();
        $second->seek(0);
        $this->assertSame("second", $second->readToEnd(), "closing one handle does not affect another");
        $second->close();
    }

    public function testReading(): void
    {
        $reader = FileHandle::fileHandleForReadingFromURL(URL::fileURL($this->existing));
        $this->assertSame(0, $reader->offsetInFile, "offsetInFile starts at 0");
        $this->assertSame("hello", $reader->read(5), "read returns the requested bytes");
        $this->assertSame(5, $reader->offsetInFile, "read advances offsetInFile");
        $this->assertSame(" world", $reader->readToEnd(), "readToEnd reads from the current offset");
        $reader->seek(6);
        $this->assertSame(6, $reader->offsetInFile, "seek moves the file pointer");
        $this->assertSame("world", $reader->availableData, "availableData reads from the current offset to the end");
        $this->assertSame(11, $reader->seekToEnd(), "seekToEnd returns the file size");
        $reader->close();
    }

    public function testCloseIsIdempotent(): void
    {
        // The destructor also calls close(); with the handle already closed it must not fail.
        $closable = FileHandle::fileHandleForReadingFromURL(URL::fileURL($this->existing));
        $closable->close();
        $closable->close();
        $this->assertTrue(true, "close is idempotent");
        unset($closable);
        $this->assertTrue(true, "destruction after an explicit close does not fail");
    }

    public function testWriting(): void
    {
        $written = $this->directory . DIRECTORY_SEPARATOR . "written.txt";
        file_put_contents($written, "");
        $writer = FileHandle::fileHandleForWritingToURL(URL::fileURL($written));
        $this->assertInstanceOf(FileHandle::class, $writer, "writing factory opens the file");
        $writer->write("data");
        $writer->synchronize();
        $this->assertSame("data", file_get_contents($written), "write persists the data");
        $writer->truncate(2);
        $writer->close();
        $this->assertSame("da", file_get_contents($written), "truncate shortens the file");
    }

    public function testUpdating(): void
    {
        $updated = $this->directory . DIRECTORY_SEPARATOR . "updated.txt";
        file_put_contents($updated, "old");
        $updater = FileHandle::fileHandleForUpdatingURL(URL::fileURL($updated));
        $updater->write("new content");
        $updater->seek(0);
        $this->assertSame("new", $updater->read(3), "updating handle can write and read back");
        $updater->close();
    }
}
