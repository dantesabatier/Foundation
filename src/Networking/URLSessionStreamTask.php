<?php

namespace Sabatier\Foundation\Networking;

use Closure;
use Sabatier\Foundation\Error;

/**
 * A URL session task that is stream-based.
 */
final class URLSessionStreamTask extends URLSessionTask
{
    /**
     * Asynchronously reads a number of bytes from the stream and calls a handler upon completion.
     * @param int $minBytes The minimum number of bytes to read.
     * @param int $maxBytes The maximum number of bytes to read.
     * @param float $timeout A timeout for reading bytes. If the read is not completed within the specified interval, the read is canceled and the completionHandler is called with an error. Pass 0 to prevent a read from timing out.
     * @param Closure(string|null, bool, Error|null): void $completionHandler The completion handler to call when all bytes are read, or an error occurs. This handler is executed on the delegate queue.
     * This completion handler takes the following parameters:
     * $data The data read from the stream.
     * $atEOF Whether the stream reached end-of-file (EOF), such that no more data can be read.
     * $error An error object that indicates why the read failed, or null if the read was successful.
     */
    public function readData(int $minBytes, int $maxBytes, float $timeout, Closure $completionHandler): void
    {
    }

    /**
     * Asynchronously writes the specified data to the stream and calls a handler upon completion.
     * @param string $data The data to be written.
     * @param float $timeout A timeout for writing bytes. If the `write` is not completed within the specified interval, the `write` is canceled and the completionHandler is called with an error. Pass 0 to prevent a `write` from timing out.
     * @param Closure(Error|null): void $completionHandler The completion handler to call when all bytes are written, or an error occurs. This handler is executed on the delegate queue.
     * This completion handler takes the following parameter:
     * $error An error object that indicates why the `write` failed, or null if the `write` was successful.
     */
    public function write(string $data, float $timeout, Closure $completionHandler): void
    {
    }

    /**
     * Completes any already enqueued reads and writes, and then invokes the urlSessionStreamTaskDidBecomeOutputStream() delegate message.
     */
    public function captureStreams(): void
    {
    }

    /**
     * Completes any enqueued reads and writes, and then closes the read side of the underlying socket.
     */
    public function closeRead(): void
    {
    }

    /**
     * Completes any enqueued reads and writes, and then closes the `write` side of the underlying socket.
     */
    public function closeWrite(): void
    {
    }

    /**
     * Completes any enqueued reads and writes, and establishes a secure connection.
     * 
     * Authentication callbacks are sent to the session's delegate using the {@see URLSessionTaskDelegate::urlSessionTaskDidReceiveChallenge()} method.
     */
    public function startSecureConnection(): void
    {
    }
}
