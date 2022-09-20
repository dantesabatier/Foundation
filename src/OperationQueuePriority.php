<?php
/**
 * Created by PhpStorm.
 * User: dante
 * Date: 01/07/20
 * Time: 04:43
 */

namespace Sabatier\Foundation;

/**
 * Class OperationQueuePriority
 * These constants let you prioritize the order in which operations execute.
 * You can use these constants to specify the relative ordering of operations that are waiting to be started in an operation queue. You should always use these constants (and not the defined value) for determining priority.
 * @package Sabatier\Foundation
 */
enum OperationQueuePriority: int
{
    /** Operations receive very low priority for execution. */
    case veryLow = -8;
    /** Operations receive low priority for execution. */
    case low = -4;
    /** Operations receive the normal priority for execution. */
    case normal = 0;
    /** Operations receive high priority for execution. */
    case high = 4;
    /** Operations receive very high priority for execution. */
    case veryHigh = 8;
}
