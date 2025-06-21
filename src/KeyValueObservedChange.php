<?php

/**
 * Created by PhpStorm.
 * User: dante
 * Date: 03/07/20
 * Time: 13:24
 */

namespace Sabatier\Foundation;

class KeyValueObservedChange
{
    /** @var KeyValueChange A value of KeyValueChange::setting indicates that the observed object has received a setValueForKey() message, or that the key-value-coding-compliant set method for the key has been invoked, or that one of the {@see KeyValueObserving::willChangeValueForKey()} or {@see KeyValueObserving::didChangeValueForKey()} methods has otherwise been invoked. A value of KeyValueChange::insertion, KeyValueChange::removal, or KeyValueChange::replacement indicates that mutating messages have been sent a key-value observing compliant collection proxy, or that one of the key-value-coding-compliant collection mutation methods for the key has been invoked, or a collection will change or did change method has been otherwise been invoked. */
    public KeyValueChange $kind = KeyValueChange::setting;
    /** @var mixed|null If the value of the kindKey entry is KeyValueChange::setting, and new was specified when the observer was registered, the value of this key is the new value for the attribute. For KeyValueChange::insertion or KeyValueChange::replacement, if new was specified when the observer was registered, the value for this key is an ArrayClass instance that contains the objects that have been inserted or replaced other objects, respectively. */
    public mixed $newValue = null;
    /** @var mixed|null If the value of the kindKey entry is KeyValueChange::setting, and old was specified when the observer was registered, the value of this key is the value before the attribute was changed. For KeyValueChange::removal or KeyValueChange::replacement, if old was specified when the observer was registered, the value is an ArrayClass instance that contains the objects that have been removed or have been replaced by other objects, respectively. */
    public mixed $oldValue = null;
    /** @var bool If the prior option was specified when the observer was registered, this notification is sent prior to a change. */
    public bool $isPrior = false;
}
