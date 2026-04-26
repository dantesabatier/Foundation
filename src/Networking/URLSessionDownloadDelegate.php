<?php

declare(strict_types=1);

namespace Sabatier\Foundation\Networking;

use Sabatier\Foundation\URL;

/**
 * A protocol that defines methods that URL session instances call on their delegates to handle task-level events specific to download tasks.
 */
interface URLSessionDownloadDelegate extends URLSessionTaskDelegate
{
    /**
     * Tells the delegate that a download task has finished downloading.
     * @param URLSession $session The session containing the download task that finished.
     * @param URLSessionDownloadTask $downloadTask The download task that finished.
     * @param URL|null $location A file URL for the temporary file. Because the file is temporary, you must either open the file for reading or move it to a permanent location in your app's sandbox container directory before returning from this delegate method.
     * If you choose to open the file for reading, you should do the actual reading in another thread to avoid blocking the delegate queue.
     */
    public function urlSessionDownloadTaskDidFinishDownloadingToURL(URLSession $session, URLSessionDownloadTask $downloadTask, ?URL $location): void;

    /**
     * Periodically informs the delegate about the download's progress.
     * @param URLSession $session The session containing the download task.
     * @param URLSessionDownloadTask $downloadTask The download task.
     * @param int $bytesWritten The number of bytes transferred since the last time this delegate method was called.
     * @param float $totalBytesWritten The total number of bytes transferred so far.
     * @param float $totalBytesExpectedToWrite The expected length of the file, as provided by the Content-Length header. If this header was not provided, the value is {@see URLSessionTransferSizeUnknown}.
     */
    public function urlSessionDownloadTaskDidWriteData(URLSession $session, URLSessionDownloadTask $downloadTask, int $bytesWritten, float $totalBytesWritten, float $totalBytesExpectedToWrite): void;
}
