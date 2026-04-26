<?php

declare(strict_types=1);

/**
 * Created by PhpStorm.
 * User: dante
 * Date: 17/07/20
 * Time: 14:34
 */

namespace Sabatier\Foundation;

/**
 * A type that can be compared for value equality.
 */
interface Equatable
{
    /**
     * Returns a Boolean value that indicates whether the receiver is equal to another given object.
     * @param mixed $other The object to be compared to the receiver. May be null, in which case this method returns false.
     * @return bool true if the receiver and other are equal, otherwise false.
     */
    public function isEqual(mixed $other): bool;
}
