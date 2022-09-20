<?php

/**
 * @author Dante Sabatier <dantesabatier@me.com>
 * @version 1.0
 * @package Sabatier\Foundation
 */

namespace Sabatier\Foundation;

/**
 * Class URLSessionTaskState
 * Constants for determining the current state of a task.
 * @package Sabatier\Foundation
 */
enum URLSessionTaskState: int
{
    /** The task is currently being serviced by the session. */
    case running = 0;
    /** The task was suspended by the app. */
    case suspended = 1;
    /** The task has received a cancel message. */
    case canceled = 2;
    /** The task has completed (without being canceled), and the task's delegate receives no further callbacks. */
    case completed = 3;
}
