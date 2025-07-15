<?php

/**
 * Created by PhpStorm.
 * User: dante
 * Date: 19/07/20
 * Time: 10:34
 */

namespace Sabatier\Foundation;

/**
 * Defines a contract for classes whose instances can be compared to determine their ordering relative to other objects.
 */
interface Comparable extends Equatable
{
    /**
     * Returns a {@see ComparisonResult} value that indicates whether the object is greater than, equal to, or less than a given value.
     * @param mixed $other The object to compare with the receiver.
     * @return ComparisonResult orderedAscending if the receiver is less than other, orderedDescending if the receiver is greater than other, or orderedSame if the receiver is equal to $other.
     */
    public function compare(mixed $other): ComparisonResult;
}
