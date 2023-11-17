<?php

/**
 * Created by PhpStorm.
 * User: dante
 * Date: 28/06/20
 * Time: 20:08
 */

namespace Sabatier\Foundation;

/**
 * The kinds of changes that can be observed.
 * These constants are returned as the value for a KeyValueChangeKindKey key in the change dictionary passed to {@see KeyValueObserving::observeValue()} indicating the type of change made.
 */
enum KeyValueChange: int
{
    /** Indicates that the value of the observed key path was set to a new value. This change can occur when observing an attribute of an object, as well as properties that specify to-one and to-many relationships. */
    case setting = 0;
    /** Indicates that an object has been inserted into the to-many relationship that is being observed. */
    case insertion = 1;
    /** Indicates that an object has been removed from the to-many relationship that is being observed. */
    case removal = 2;
    /** Indicates that an object has been replaced in the to-many relationship that is being observed. */
    case replacement = 3;
}
