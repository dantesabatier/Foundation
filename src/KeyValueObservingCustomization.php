<?php

/**
 * Created by PhpStorm.
 * User: dante
 * Date: 15/07/20
 * Time: 05:44
 */

namespace Sabatier\Foundation;

interface KeyValueObservingCustomization
{
    /**
     * Returns a set of key paths for properties whose values affect the value of the specified key.
     * @param string $key
     * @return Set<string>
     */
    public static function keyPathsForValuesAffectingValueForKey(string $key): Set;

    /**
     * Returns a Boolean value that indicates whether the observed object supports automatic key-value observation for the given key.
     */
    public static function automaticallyNotifiesObserversForKey(string $key): bool;
}
