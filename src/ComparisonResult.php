<?php

declare(strict_types=1);

/**
 * Created by PhpStorm.
 * User: dante
 * Date: 19/07/20
 * Time: 11:11
 */

namespace Sabatier\Foundation;

/**
 * Constants that indicate sort order.
 */
enum ComparisonResult: int
{
    /** The left operand is smaller than the right operand. */
    case orderedAscending = -1;
    /** The two operands are equal. */
    case orderedSame = 0;
    /** The left operand is greater than the right operand. */
    case orderedDescending = 1;
}
