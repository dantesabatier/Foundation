<?php

declare(strict_types=1);

namespace Sabatier\Foundation\Networking;

/**
 * Constants indicating how a data or upload session should proceed after receiving the initial headers.
 */
enum URLSessionResponseDisposition: int
{
    /** Cancel the load. Using this disposition is equivalent to calling {@see URLSessionTask::cancel()} on the task. */
    case cancel = 0;
    /** Allow the load operation to continue. */
    case allow = 1;
    /** Convert the response for this request to use a {@see URLSessionDownloadTask}. */
    case becomeDownload = 2;
    /** Convert the response for this request to use a URLSessionStreamTask. */
    case becomeStream = 3;
}
