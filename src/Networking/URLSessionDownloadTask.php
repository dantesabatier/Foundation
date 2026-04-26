<?php

declare(strict_types=1);

/**
 * Created by PhpStorm.
 * User: dante
 * Date: 08/05/20
 * Time: 13:13
 */
namespace Sabatier\Foundation\Networking;

use Closure;

/**
 * A URL session task that stores downloaded data to a file.
 */
final class URLSessionDownloadTask extends URLSessionTask
{
    /**
     * Cancels a download and calls a callback with resume data for later use.
     * @param Closure(string|null): void $completionHandler A completion handler that is called when the download has been successfully canceled.
     */
    public function cancelByProducingResumeData(Closure $completionHandler): void
    {
    }
}
