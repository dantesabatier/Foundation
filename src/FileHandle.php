<?php

/** @noinspection PhpMixedReturnTypeCanBeReducedInspection */

declare(strict_types=1);

namespace Sabatier\Foundation;

use Exception;

/**
 * An object-oriented wrapper for a file descriptor.
 */
final class FileHandle extends ObjectClass
{
    /** @var string Raised by FileHandle if attempts to determine a file-handle type fail or if attempts to read from a file or channel fail. */
    public const string fileHandleOperationException = "FileHandleOperationException";
    private static ?FileHandle $standardError = null;
    private static ?FileHandle $standardInput = null;
    private static ?FileHandle $standardOutput = null;
    /** @var string The data currently available in the receiver. The data currently available through the receiver, up to the maximum size that can be represented by a string. If the receiver is a file, this method returns the data obtained by reading the file from the current file pointer to the end of the file. If the receiver is a communications channel, this method reads up to a buffer of data and returns it; if no data is available, the method blocks. Returns an empty data object if the end of file is reached. This method raises {@see fileHandleOperationException} if attempts to determine the file-handle type fail or if attempts to read from the file or channel fail. */
    public string $availableData {
        /** @noinspection PhpUnhandledExceptionInspection */
        get => $this->read(PHP_INT_MAX) ?? "";
    }
    /** @var int The position of the file pointer within the file. */
    public int $offsetInFile {
        /** @throws Exception Throws an error if accessed on a file handle representing a pipe or socket, or if the file descriptor is closed. */
        get => unsafe_value(fn(): int => ftell($this->rawHandle));
    }

    /**
     * @param resource $rawHandle
     */
    private function __construct(public readonly mixed $rawHandle)
    {
    }

    /**
     * @throws Exception
     */
    public function __destruct()
    {
        $this->close();
    }

    /**
     * Returns a file handle initialized for reading the file, device, or named socket at the specified URL.
     *
     * The file pointer is set to the beginning of the file. You cannot write data to the returned file handle object. Use the {@see readToEnd()} or {@see read()} methods to read data from it.
     * When using this method to create a file handle object, the file handle owns its associated file descriptor and is responsible for closing it.
     * @param URL $url The URL of the file, device, or named socket to access.
     * @return FileHandle|null The initialized file handle object or null if no file exists at url.
     */
    public static function fileHandleForReadingFromURL(URL $url): ?FileHandle
    {
        return new FileHandle(unsafe_value(fn(): mixed => fopen($url->path, "r")));
    }

    /**
     * Returns a file handle initialized for writing to the file, device, or named socket at the specified URL.
     *
     * The file pointer is set to the beginning of the file. The returned object responds only to {@see write()}.
     * When using this method to create a file handle object, the file handle owns its associated file descriptor and is responsible for closing it.
     * @param URL $url The URL of the file, device, or named socket to access.
     * @return FileHandle|null The initialized file handle object or null if the file cannot be opened for writing.
     */
    public static function fileHandleForWritingToURL(URL $url): ?FileHandle
    {
        return new FileHandle(unsafe_value(fn(): mixed => fopen($url->path, "w")));
    }

    /**
     * Returns a file handle initialized for reading and writing to the file, device, or named socket at the specified URL.
     *
     * The file pointer is set to the beginning of the file. The returned object responds to both {@see read()}... messages and {@see write()}.
     * When using this method to create a file handle object, the file handle owns its associated file descriptor and is responsible for closing it.
     * @param URL $url The URL of the file, device, or named socket to access.
     * @return FileHandle|null The initialized file handle object or null if the file cannot be opened for updating.
     */
    public static function fileHandleForUpdatingURL(URL $url): ?FileHandle
    {
        return new FileHandle(unsafe_value(fn(): mixed => fopen($url->path, "w+")));
    }

    /**
     * The shared file handle associated with the standard error file.
     *
     * Conventionally, this is a terminal device where the system sends error messages. There's one standard error file handle per process; it's a shared instance.
     * When using this method to create a file handle object, the file handle owns its associated file descriptor and is responsible for closing it.
     * @return FileHandle The shared file handle associated with the standard error file.
     */
    public static function standardError(): FileHandle
    {
        return self::$standardError ??= new FileHandle(unsafe_value(fn(): mixed => fopen("php://stderr", "w")));
    }

    /**
     * The file handle associated with the standard input file.
     *
     * Conventionally, this is a terminal device on which the user enters a stream of data. There's one standard input file handle per process; it's a shared instance.
     * When using this method to create a file handle object, the file handle owns its associated file descriptor and is responsible for closing it.
     * @return FileHandle The shared file handle associated with the standard input file.
     */
    public static function standardInput(): FileHandle
    {
        return self::$standardInput ??= new FileHandle(unsafe_value(fn(): mixed => fopen("php://stdin", "r")));
    }

    /**
     * The file handle associated with the standard output file.
     *
     * Conventionally, this is a terminal device that receives a stream of data from a program. There's one standard output file handle per process; it's a shared instance.
     * When using this method to create a file handle object, the file handle owns its associated file descriptor and is responsible for closing it.
     * @return FileHandle The shared file handle associated with the standard output file.
     */
    public static function standardOutput(): FileHandle
    {
        return self::$standardOutput ??= new FileHandle(unsafe_value(fn(): mixed => fopen("php://stdout", "w")));
    }

    /**
     * Reads the available data synchronously up to the end of the file or maximum number of bytes.
     *
     * This method invokes {@see read()} as part of its implementation.
     * @return string|null The data available through the file handle up to the maximum size that can be represented by a string or, if a communications channel, until an end-of-file indicator is returned.
     * @throws Exception
     */
    public function readToEnd(): ?string
    {
        return $this->read(PHP_INT_MAX);
    }

    /**
     * Reads data synchronously up to the specified number of bytes.
     *
     * If the handle represents a file, this method returns the data obtained by reading length bytes starting at the current file pointer. If length bytes aren't available, this method returns the data from the current file pointer to the end of the file. If the handle is a communications channel, the method reads up-to-length bytes from the channel. Returns an empty string if the handle is at the file's end or if the communications channel returns an end-of-file indicator.
     * @param int $count The number of bytes to read from the file handle.
     * @return string|null The data available through the receiver up to a maximum of length bytes, or the maximum size that can be represented by a string, whichever is the smaller.
     * @throws Exception This method throws an error if attempts to determine the file-handle type fail or if attempts to read from the file or channel fail.
     */
    public function read(int $count): ?string
    {
        $data = "";
        while (strlen($data) < $count) {
            if (!($buffer = unsafe_value(fn(): false|string => fread($this->rawHandle, min($count - strlen($data), 8192))))) {
                break;
            }
            $data .= $buffer;
        }
        return empty($data) ? null : $data;
    }

    /**
     * Writes the specified data synchronously to the file handle.
     *
     * If the handle represents a file, writing takes place at the file pointer's current position. After it writes the data, the method advances the file pointer by the number of bytes written.
     * @param string $data The data to write to the file handle.
     * @throws Exception This method throws an error if the file descriptor is closed or isn't valid, if the handle represents an unconnected pipe or socket endpoint, if there isn't any free space on the file system, or if any other writing error occurs.
     */
    public function write(string $data): void
    {
        unsafe_value(fn(): int|false => fwrite($this->rawHandle, $data));
    }

    /**
     * Places the file pointer at the end of the file referenced by the file handle and returns the new file offset.
     * @return int The file offset with the file pointer at the end of the file. This is therefore equal to the size of the file.
     * @throws Exception Throws an error if called on a file handle representing a pipe or socket, or if the file descriptor is closed.
     */
    public function seekToEnd(): int
    {
        unsafe_value(fn(): int => fseek($this->rawHandle, 0, SEEK_END));
        return $this->offsetInFile;
    }

    /**
     * Moves the file pointer to the specified offset within the file.
     * @param int $offset The offset to seek to.
     * @throws Exception Throws an error if called on a file handle representing a pipe or socket, if the file descriptor is closed, or if any other error occurs while seeking.
     */
    public function seek(int $offset): void
    {
        unsafe_value(fn(): int => fseek($this->rawHandle, $offset));
    }

    /**
     * Disallows further access to the represented file or communications channel and signals end of file on communications channels that permit writing.
     * @throws Exception
     */
    public function close(): void
    {
        if (!is_resource($this->rawHandle)) {
            return;
        }
        /** @psalm-suppress InvalidPropertyAssignmentValue */
        unsafe_value(fn(): bool => fclose($this->rawHandle));
    }

    /**
     * Causes all in-memory data and attributes of the file represented by the file handle to write to permanent storage.
     *
     * Programs that require the file to always be in a known state should call this method. An invocation of this method doesn't return until memory is flushed.
     * @throws Exception
     */
    public function synchronize(): void
    {
        unsafe_value(fn(): bool => fsync($this->rawHandle));
    }

    /**
     * Truncates or extends the file represented by the file handle to a specified offset within the file and puts the file pointer at that position.
     *
     * If the file is extended (if `$offset` is beyond the current end of file), the added characters are null bytes.
     * @param int $offset The offset within the file that marks the new end of the file.
     * @throws Exception
     */
    public function truncate(int $offset): void
    {
        unsafe_value(fn(): bool => ftruncate($this->rawHandle, $offset));
    }
}
