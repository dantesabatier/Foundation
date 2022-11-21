<?php

/**
 * Created by PhpStorm.
 * User: dante
 * Date: 03/07/20
 * Time: 09:33
 */

namespace Sabatier\Foundation;

/**
 * The values that can be returned in a change dictionary.
 *
 * These constants are passed to {@see KeyValueObserving::addObserver()} and determine the values that are returned as part of the change dictionary passed to an {@see KeyValueObserving::observeValue()}. You can pass 0 if you require no change dictionary values.
 */
class KeyValueObservingOptions
{
    /** @var int Indicates that the change dictionary should provide the new attribute value, if applicable. */
    final const new = 1;
    /** @var int Indicates that the change dictionary should contain the old attribute value, if applicable. */
    final const old = 2;
    /** @var int If specified, a notification should be sent to the observer immediately, before the observer registration method even returns.
     * The change dictionary in the notification will always contain an newKey entry if new is also specified but will never contain an oldKey entry. (In an initial notification the current value of the observed property may be old, but it's new to the observer.) You can use this option instead of explicitly invoking, at the same time, code that is also invoked by the observer's {@see KeyValueObserving::observeValue()} method. When this option is used with {@see KeyValueObserving::addObserver()} a notification will be sent for each indexed object to which the observer is being added.
     */
    final const initial = 4;
    /** @var int Whether separate notifications should be sent to the observer before and after each change, instead of a single notification after the change.
     * The change dictionary in a notification sent before a change always contains an notificationIsPriorKey entry whose value is a Number object that contains the Boolean value true, but never contains an newKey entry. When this option is specified the change dictionary in a notification sent after a change contains the same entries that it would contain if this option were not specified. You can use this option when the observer's own key-value observing-compliance requires it to invoke one of the willChange... methods for one of its own properties, and the value of that property depends on the value of the observed object's property. (In that situation it's too late to easily invoke willChange... properly in response to receiving an {@see KeyValueObserving::observeValue()} message after the change.)
     */
    final const prior = 8;
}
