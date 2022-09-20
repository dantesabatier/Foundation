<?php
/**
 * Created by PhpStorm.
 * User: dante
 * Date: 19/07/20
 * Time: 10:34
 */

namespace Sabatier\Foundation;

/**
 * Interface Comparable
 * @package Sabatier\Foundation
 */
interface Comparable extends Equatable
{
    /**
     * Returns a {@see ComparisonResult} value that indicates whether the object is greater than, equal to, or less than a given value.
     * @param mixed $other The object to compare with the receiver.
     * @return ComparisonResult {@see ComparisonResult::orderedAscending} if the receiver is less than other, {@see ComparisonResult::orderedDescending} if the receiver is greater than other, or {@see ComparisonResult::orderedSame} if the receiver is equal to $other.
     */
    public function compare(mixed $other): ComparisonResult;
}
