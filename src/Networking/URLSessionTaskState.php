<?php

declare(strict_types=1);

namespace Sabatier\Foundation\Networking;

/**
 * Constants for determining the current state of a task.
 */
enum URLSessionTaskState: int
{
    /** The task is currently being serviced by the session. */
    case running = 0;
    /** The task was suspended by the app. */
    case suspended = 1;
    /** The task has received a cancel message. */
    case canceling = 2;
    /** The task has completed (without being canceled), and the task's delegate receives no further callbacks. */
    case completed = 3;
}
