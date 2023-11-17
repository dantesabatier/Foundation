<?php

namespace Sabatier\Foundation;

/**
 * The keys that can appear in the change dictionary.
 * These constants are used as keys in the change dictionary passed to {@see KeyValueObserving::observeValue()}.
 */
class KeyValueChangeKey
{
    /** @var string If the value of the kindKey entry is KeyValueChange::insertion, KeyValueChange::removal, or KeyValueChange::replacement, the value of this key is an IndexSet object that contains the indexes of the inserted, removed, or replaced objects. */
    final const indexesKey = "indexesKey";
    /** @var string A {@see Number} object that contains a value corresponding to one of the KeyValueChange enums, indicating what sort of change has occurred. A value of KeyValueChange::setting indicates that the observed object has received a setValueForKey() message, or that the key-value-coding-compliant set method for the key has been invoked, or that one of the {@see KeyValueObserving::willChangeValueForKey()} or {@see KeyValueObserving::didChangeValueForKey()} methods has otherwise been invoked. A value of KeyValueChange::insertion, KeyValueChange::removal, or KeyValueChange::replacement indicates that mutating messages have been sent a key-value observing compliant collection proxy, or that one of the key-value-coding-compliant collection mutation methods for the key has been invoked, or a collection will change or did change method has been otherwise been invoked. You can use the {@see Number::$intValue} method on the Number object to retrieve the value of the change kind. */
    final const kindKey = "kindKey";
    /** @var string If the value of the kindKey entry is KeyValueChange::setting, and new was specified when the observer was registered, the value of this key is the new value for the attribute. For KeyValueChange::insertion or KeyValueChange::replacement, if new was specified when the observer was registered, the value for this key is an ArrayClass instance that contains the objects that have been inserted or replaced other objects, respectively. */
    final const newKey = "newKey";
    /** @var string If the prior option was specified when the observer was registered this notification is sent prior to a change. The change dictionary contains an notificationIsPriorKey entry whose value is a Number object that contains the Boolean value true. */
    final const notificationIsPriorKey = "notificationIsPriorKey";
    /** @var string If the value of the kindKey entry is KeyValueChange::setting, and old was specified when the observer was registered, the value of this key is the value before the attribute was changed. For KeyValueChange::removal or KeyValueChange::replacement, if old was specified when the observer was registered, the value is an ArrayClass instance that contains the objects that have been removed or have been replaced by other objects, respectively. */
    final const oldKey = "oldKey";
}