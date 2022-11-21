<?php

namespace Sabatier\Foundation\Networking;

use Sabatier\Foundation\URL;

/**
 * A protocol that defines methods that URL session instances call on their delegates to handle task-level events specific to download tasks.
 */
interface URLSessionDownloadDelegate extends URLSessionTaskDelegate
{
    public function urlSessionDownloadTaskDidFinishDownloadingToURL(URLSession $session, URLSessionDownloadTask $downloadTask, ?URL $location): void;

    public function urlSessionDownloadTaskDidWriteData(URLSession $session, URLSessionDownloadTask $downloadTask, int $bytesWritten, float $totalBytesWritten, float $totalBytesExpectedToWrite): void;
}
