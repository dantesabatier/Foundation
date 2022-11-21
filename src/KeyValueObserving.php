<?php

/**
 * Created by PhpStorm.
 * User: dante
 * Date: 26/06/20
 * Time: 10:02
 */

namespace Sabatier\Foundation;

use Closure;
use JetBrains\PhpStorm\ExpectedValues;

/**
 * An informal protocol that objects adopt to be notified of changes to the specified properties of other objects.
 */
interface KeyValueObserving
{
    /**
     * @param Closure(mixed, KeyValueObservedChange): void|null $handler
     */
    public function observe(string $keyPath, #[ExpectedValues(flagsFromClass: KeyValueObservingOptions::class)] int $options = KeyValueObservingOptions::new, Closure $handler = null): KeyValueObservation;

    /**
     * Informs the observing object when the value at the specified key path relative to the observed object has changed.
     * @param string $keyPath The key path, relative to object, to the value that has changed.
     * @param mixed $object The source object of the key path keyPath.
     * @param KeyValueObservedChange $change A dictionary that describes the changes that have been made to the value of the property at the key path keyPath relative to object.
     * @param mixed|null $context The value that was provided when the observer was registered to receive key-value observation notifications.
     */
    public function observeValue(string $keyPath, mixed $object, KeyValueObservedChange $change, mixed $context = null): void;

    /**
     * Registers the observer object to receive KVO notifications for the key path relative to the object receiving this message.
     * @param object $observer The object to register for KVO notifications.
     * The observer must implement the key-value observing method {@see observeValue()}.
     * @param string $keyPath The key path, relative to the object receiving this message, of the property to observe.
     * @param int $options A combination of the {@see KeyValueObservingOptions} values that specifies what is included in observation notifications.
     * @param mixed|null $context Arbitrary data that is passed to observer in {@see observeValue()}.
     */
    public function addObserver(object $observer, string $keyPath, #[ExpectedValues(flagsFromClass: KeyValueObservingOptions::class)] int $options = KeyValueObservingOptions::new, mixed $context = null): void;

    /**
     * Stops the observer object from receiving change notifications for the property specified by the key path relative to the object receiving this message.
     * @param object $observer The object to remove as an observer.
     * @param string $keyPath A key-path, relative to the object receiving this message, for which observer is registered to receive KVO change notifications.
     * @param mixed|null $context Arbitrary data that more specifically identifies the observer to be removed.
     */
    public function removeObserver(object $observer, string $keyPath, mixed $context = null): void;

    /**
     * Informs the observed object that the value of a given property is about to change.
     * @param string $key The name of the property that will change.
     * @param KeyValueChange $changeKind The change kind.
     * @param mixed|null $changedValue The changed value.
     */
    public function willChangeValueForKey(string $key, KeyValueChange $changeKind = KeyValueChange::setting, mixed $changedValue = null): void;

    /**
     * Informs the observed object that the value of a given property has changed.
     * @param string $key The name of the property that changed.
     * @param KeyValueChange $changeKind The change kind.
     * @param mixed|null $changedValue The changed value.
     */
    public function didChangeValueForKey(string $key, KeyValueChange $changeKind = KeyValueChange::setting, mixed $changedValue = null): void;
}
