<?php

namespace Sabatier\Foundation;

/**
 * Interface URLSessionDownloadDelegate
 * A protocol that defines methods that URL session instances call on their delegates to handle task-level events specific to download tasks.
 * @package Sabatier\Foundation
 */
interface URLSessionDownloadDelegate extends URLSessionTaskDelegate
{
    public function urlSessionDownloadTaskDidFinishDownloadingToURL(URLSession $session, URLSessionDownloadTask $downloadTask, ?URL $location): void;
}
