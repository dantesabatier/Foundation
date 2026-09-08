<?php

/**
 * Created by PhpStorm.
 * User: dante
 * Date: 13/06/20
 * Time: 18:42
 */

declare(strict_types=1);

namespace Sabatier\Foundation;

use Closure;
use InvalidArgumentException;
use JetBrains\PhpStorm\ExpectedValues;
use JetBrains\PhpStorm\Pure;
use JsonSerializable;
use Override;
use ReflectionClass;
use ReflectionProperty;

/**
 * The root class of most class hierarchies, from which subclasses inherit a basic interface to the runtime system and the ability to behave as Objective-C objects.
 */
class ObjectClass implements ObjectProtocol, KeyValueObserving, KeyValueCoding, Comparable, JsonSerializable
{
    /** @var KeyValueObservance[] $observances */
    private array $observances = [];
    /** @var array<string, mixed> $valuesBeingChanged The value each key held when willChangeValueForKey() announced the change, so didChangeValueForKey() can report the one that was replaced rather than the one replacing it. */
    private array $valuesBeingChanged = [];
    /** @var array<string, true> $keysBeingAnnounced The keys whose dependents are currently being notified, used as a re-entry guard: a model that declares a dependency cycle would otherwise recurse until the stack overflows. */
    private array $keysBeingAnnounced = [];
    /** @var array<string, mixed> */
    public static array $staticAssociatedValues = [];
    /** @var array<string, mixed> */
    public array $associatedValues = [];
    public int $hash {
        get => spl_object_id($this);
    }
    public string $class {
        get => get_class($this);
    }
    public string $superclass {
        get => get_parent_class($this);
    }
    public string $description {
        get => sprintf("<%s %s>", $this->class, $this->hash);
    }
    public string $debugDescription {
        get => sprintf("<%s %s>", $this->class, $this->hash);
    }
    public string $canonicalDescription {
        get => $this->class;
    }

    /**
     * Initializes the class before it receives its first message.
     */
    public static function initialize(): void
    {
    }

    #[Pure]
    #[Override]
    final public function isKind(string $class): bool
    {
        return is_a($this, $class, true);
    }

    #[Override]
    final public function isMember(string $class): bool
    {
        return $this::class === $class;
    }

    #[Pure]
    #[Override]
    final public function isSubclass(string $class): bool
    {
        return is_subclass_of($this, $class);
    }

    #[Override]
    public function responds(string $selector): bool
    {
        return method_exists($this, $selector);
    }

    #[Pure]
    #[Override]
    final public function hasProperty(string $key): bool
    {
        return property_exists($this, $key);
    }

    #[Override]
    final public function conforms(string $protocol): bool
    {
        return isset(class_implements($this)[$protocol]);
    }

    #[Pure]
    #[Override]
    public static function instancesRespond(string $selector): bool
    {
        return method_exists(static::class, $selector);
    }

    #[Override]
    public function perform(string $selector, array $arguments = []): mixed
    {
        if ($this->responds($selector)) {
            return $this->$selector(...$arguments);
        }
        $this->doesNotRecognizeSelector($selector);
    }

    /**
     * Handles messages the receiver doesn't recognize.
     *
     * The runtime system invokes this method whenever an object receives a $selector message it can't respond to or forward. This method, in turn, raises an {@see InvalidArgumentException} and generates an error message.
     * @param string $selector A Selector that identifies a method not implemented or recognized by the receiver.
     */
    public function doesNotRecognizeSelector(string $selector): never
    {
        throw new InvalidArgumentException(sprintf("%s %s() unrecognized selector sent to instance", $this->debugDescription, $selector));
    }

    /**
     * Overridden by subclasses to forward messages to other objects.
     * @param Invocation $invocation The invocation to forward.
     */
    public function forwardInvocation(Invocation $invocation): void
    {
        $this->doesNotRecognizeSelector($invocation->selector);
    }

    #[Override]
    public function compare(mixed $other): ComparisonResult
    {
        request_concrete_implementation($this, __FUNCTION__);
    }

    #[Override]
    public function isEqual(mixed $other): bool
    {
        if ($other instanceof ObjectClass) {
            return $this->hash === $other->hash;
        }
        return false;
    }

    #[Override]
    public function observe(string $keyPath, #[ExpectedValues(flagsFromClass: KeyValueObservingOptions::class)] int $options = KeyValueObservingOptions::new, ?Closure $handler = null): KeyValueObservation
    {
        $observation = new KeyValueObservation($this, $keyPath);
        $observance = new KeyValueObservance($observation, $keyPath, $options, handler: $handler);
        $this->observances[] = $observance;
        $this->notifyInitialValue($observance);
        return $observation;
    }

    #[Override]
    public function observeValue(string $keyPath, mixed $object, KeyValueObservedChange $change, mixed $context = null): void
    {
    }

    #[Override]
    public function addObserver(object $observer, string $keyPath, #[ExpectedValues(flagsFromClass: KeyValueObservingOptions::class)] int $options = KeyValueObservingOptions::new, mixed $context = null): void
    {
        if (static::automaticallyNotifiesObserversForKey($keyPath)) {
            $observance = new KeyValueObservance($observer, $keyPath, $options, $context);
            $this->observances[] = $observance;
            $this->notifyInitialValue($observance);
        }
    }

    /**
     * Sends the one-off notification {@see KeyValueObservingOptions::initial} asks for, reporting the value the key already holds so an observer can prime itself from the same code path that handles later changes.
     *
     * @param KeyValueObservance $observance The observance just registered.
     */
    private function notifyInitialValue(KeyValueObservance $observance): void
    {
        if (!($observance->options & KeyValueObservingOptions::initial)) {
            return;
        }
        $keyPath = $observance->keyPath;
        $change = new KeyValueObservedChange();
        if ($observance->options & KeyValueObservingOptions::new) {
            $change->newValue = $this->valueForKeyPath($keyPath);
        }
        $observer = $observance->observer;
        if ($observer instanceof KeyValueObservation) {
            if ($handler = $observance->handler) {
                $handler($this, $change);
            }
        } elseif ($observer instanceof KeyValueObserving) {
            $observer->observeValue($keyPath, $this, $change, $observance->context);
        }
    }

    #[Override]
    public function removeObserver(object $observer, string $keyPath, mixed $context = null): void
    {
        if ($observance = array_find($this->observances, fn(KeyValueObservance $observance): bool => $observance->observer === $observer && $observance->keyPath === $keyPath)) {
            array_remove($this->observances, $observance);
        }
    }

    #[Override]
    public function willChangeValueForKey(string $key, KeyValueChange $changeKind = KeyValueChange::setting, mixed $changedValue = null): void
    {
        // Remember what the key holds before it is overwritten: this is the only moment the previous value is still readable, and didChangeValueForKey() needs it to report oldValue. Guarded by isset() rather than hasProperty(), because a declared but uninitialised typed property answers true to the latter and throws when read — which is the state every description object is in while it is being populated.
        $wantsOldValue = array_any($this->observances, fn(KeyValueObservance $candidate): bool => $candidate->keyPath === $key && ($candidate->options & KeyValueObservingOptions::old) !== 0);
        if ($wantsOldValue && isset($this->$key)) {
            $this->valuesBeingChanged[$key] = $this->$key;
        }
        foreach ($this->observances as $observance) {
            $keyPath = $observance->keyPath;
            // Only an observer that asked for the pre-change notification gets one. Notifying every observance here sent two notifications for a single change to observers that never requested the pair, and the second one — the real, post-change notification — arrived indistinguishable from the first except for isPrior, which those observers have no reason to read.
            if ($keyPath === $key && ($observance->options & KeyValueObservingOptions::prior)) {
                $change = new KeyValueObservedChange();
                $change->kind = $changeKind;
                if ($observance->options & KeyValueObservingOptions::new) {
                    // A collection mutation reports the members being inserted or removed, and this is the only notification that can: after the change they are no longer readable from the collection, so didChangeValueForKey() — which reports what the key now holds — would answer with the survivors. A plain setting reports nothing here, matching the change dictionary the prior option documents, which "never contains an newKey entry".
                    $change->newValue = match ($changeKind) {
                        KeyValueChange::insertion, KeyValueChange::removal => $changedValue,
                        default => null
                    };
                }
                if ($observance->options & KeyValueObservingOptions::old) {
                    $change->oldValue = $this->valuesBeingChanged[$key] ?? null;
                }
                $change->isPrior = true;
                $observer = $observance->observer;
                if ($observer instanceof KeyValueObservation) {
                    if ($handler = $observance->handler) {
                        $handler($this, $change);
                    }
                } elseif ($observer instanceof KeyValueObserving) {
                    $observer->observeValue($keyPath, $this, $change, $observance->context);
                }
            }
        }
        $this->announceToDependents($key, fn(string $dependentKey) => $this->willChangeValueForKey($dependentKey));
    }

    #[Override]
    public function didChangeValueForKey(string $key, KeyValueChange $changeKind = KeyValueChange::setting, mixed $changedValue = null): void
    {
        foreach ($this->observances as $observance) {
            $options = $observance->options;
            $keyPath = $observance->keyPath;
            if ($keyPath === $key) {
                $change = new KeyValueObservedChange();
                $change->kind = $changeKind;
                if ($options & KeyValueObservingOptions::new) {
                    $change->newValue = $this->valueForKey($key);
                }
                if ($options & KeyValueObservingOptions::old) {
                    // The value willChangeValueForKey() recorded, not $changedValue: callers pass the value being written, which is the new one, so reporting that here labeled the replacement as the replaced. A collection mutation is the exception — it has no scalar property to read back and passes the inserted or removed members as $changedValue, which is what "old" means for it.
                    $change->oldValue = match (true) {
                        array_key_exists($key, $this->valuesBeingChanged) => $this->valuesBeingChanged[$key],
                        $changeKind === KeyValueChange::insertion, $changeKind === KeyValueChange::removal => $changedValue,
                        default => null
                    };
                }
                if ($options & KeyValueObservingOptions::prior) {
                    $change->isPrior = false;
                }
                $observer = $observance->observer;
                if ($observer instanceof KeyValueObservation) {
                    if ($handler = $observance->handler) {
                        $handler($this, $change);
                    }
                } elseif ($observer instanceof KeyValueObserving) {
                    $observer->observeValue($keyPath, $this, $change, $observance->context);
                }
            }
        }
        unset($this->valuesBeingChanged[$key]);
        $this->announceToDependents($key, fn(string $dependentKey) => $this->didChangeValueForKey($dependentKey));
    }

    /**
     * Repeats a change announcement for every observed key whose value is derived from `$key`.
     *
     * This inverts {@see keyPathsForValuesAffectingValueForKey()}, which maps a derived key to its ingredients: the notification machinery needs the opposite direction, and only the keys actually being observed are worth asking about. A key already being announced is skipped, so a model that declares a dependency cycle settles instead of recursing until the stack overflows.
     * @param string $key The key that just changed.
     * @param Closure(string): void $announce Repeats the announcement for one dependent key.
     */
    private function announceToDependents(string $key, Closure $announce): void
    {
        if (isset($this->keysBeingAnnounced[$key])) {
            return;
        }
        $this->keysBeingAnnounced[$key] = true;
        try {
            $announced = [];
            foreach ($this->observances as $observance) {
                $candidate = $observance->keyPath;
                if ($candidate === $key || isset($announced[$candidate]) || isset($this->keysBeingAnnounced[$candidate])) {
                    continue;
                }
                if (static::keyPathsForValuesAffectingValueForKey($candidate)->containsElement($key)) {
                    $announced[$candidate] = true;
                    $announce($candidate);
                }
            }
        } finally {
            unset($this->keysBeingAnnounced[$key]);
        }
    }

    #[Override]
    public static function keyPathsForValuesAffectingValueForKey(string $key): Set
    {
        $selector = "keyPathsForValuesAffecting" . ucfirst($key);
        if (static::instancesRespond($selector)) {
            return static::$selector();
        }
        return new Set();
    }

    #[Override]
    public static function automaticallyNotifiesObserversForKey(string $key): bool
    {
        $selector = "automaticallyNotifiesObserversOf" . ucfirst($key);
        if (static::instancesRespond($selector)) {
            return static::$selector();
        }
        return true;
    }

    #[Override]
    public function validateValueForKey(mixed &$value, string $key): bool
    {
        if ($value instanceof SensitiveValue) {
            return false;
        }
        $selector = "validate" . ucfirst($key);
        if ($this->responds($selector)) {
            return $this->perform($selector, [&$value]);
        }
        return true;
    }

    #[Override]
    public function validateValueForKeyPath(mixed &$value, string $keyPath): bool
    {
        $idx = strpos($keyPath, ".");
        if (!$idx) {
            return $this->validateValueForKey($value, $keyPath);
        }
        $key = substring_to_index($keyPath, $idx);
        $obj = $this->valueForKeyPath($key);
        if ($obj === null) {
            return false;
        }
        $keyPath = substring_from_index($keyPath, $idx + 1);
        if (!$obj instanceof KeyValueCoding) {
            throw new UndefinedKeyException(sprintf("%s %s() \"%s\" is not key value coding compliant for the key \"%s\"", $this->debugDescription, __FUNCTION__, typeof($value), $keyPath));
        }
        return $obj->validateValueForKeyPath($value, $keyPath);
    }

    #[Override]
    public function valueForUndefinedKey(string $key): mixed
    {
        throw new UndefinedKeyException(sprintf("%s is not key value coding compliant for the key \"%s\"", $this->debugDescription, $key));
    }

    #[Override]
    public function setValueForUndefinedKey(mixed $value, string $key): void
    {
        throw new UndefinedKeyException(sprintf("%s is not key value coding compliant for the key \"%s\"", $this->debugDescription, $key));
    }

    #[Override]
    public function valueForKey(string $key): mixed
    {
        if ($this->hasProperty($key)) {
            return $this->$key;
        }
        return $this->valueForUndefinedKey($key);
    }

    #[Override]
    public function setValueForKey(mixed $value, string $key): void
    {
        if (!$this->validateValueForKey($value, $key)) {
            return;
        }
        if ($this->hasProperty($key)) {
            // Assigning a value is a setting, not a replacement: replacement describes an indexed element swapped inside a collection, which is what FaultingSet reports for its own mutations.
            $this->willChangeValueForKey($key, KeyValueChange::setting, $value);
            $this->$key = $value;
            $this->didChangeValueForKey($key, KeyValueChange::setting, $value);
            return;
        }
        $this->setValueForUndefinedKey($value, $key);
    }

    #[Override]
    public function valueForKeyPath(string $keyPath): mixed
    {
        $components = components_from_key_path($keyPath);
        $key = $components->key;
        if ($key === "" || $key === $keyPath) {
            return $this->valueForKey($keyPath);
        }
        $obj = $this->valueForKeyPath($key);
        if ($obj === null) {
            return null;
        }
        $remainderPath = $components->remainderPath;
        if (!$remainderPath) {
            return null;
        }
        if (!$obj instanceof KeyValueCoding) {
            throw new UndefinedKeyException(sprintf("%s %s() \"%s\" is not key value coding compliant for the key \"%s\"", $this->debugDescription, __FUNCTION__, typeof($obj), $remainderPath));
        }
        return $obj->valueForKeyPath($remainderPath);
    }

    #[Override]
    public function setValueForKeyPath(mixed $value, string $keyPath): void
    {
        if (!$this->validateValueForKeyPath($value, $keyPath)) {
            return;
        }
        $idx = strpos($keyPath, ".");
        if (!$idx) {
            $this->setValueForKey($value, $keyPath);
            return;
        }
        $key = substring_to_index($keyPath, $idx);
        $obj = $this->valueForKeyPath($key);
        if ($obj === null) {
            return;
        }
        $keyPath = substring_from_index($keyPath, $idx + 1);
        if (!$obj instanceof KeyValueCoding) {
            throw new UndefinedKeyException(sprintf("%s %s() \"%s\" is not key value coding compliant for the key \"%s\"", $this->debugDescription, __FUNCTION__, typeof($value), $keyPath));
        }
        $obj->setValueForKeyPath($value, $keyPath);
    }

    #[Override]
    public function dictionaryWithValues(ArrayClass $keys): Dictionary
    {
        $reflectionClass = new ReflectionClass($this);
        $properties = new ArrayClass($reflectionClass->getProperties(ReflectionProperty::IS_PUBLIC))->filter(fn(ReflectionProperty $property): bool => (bool)count($property->getAttributes(Sensitive::class)))->reduce(new Dictionary(),
            /**
             * @param Dictionary<mixed> $initial
             * @param ReflectionProperty $property
             * @return Dictionary<mixed>
             */
            function (Dictionary $initial, ReflectionProperty $property): Dictionary {
                $initial[$property->name] = $property;
                return $initial;
            });
        return $keys->reduce(new Dictionary(),
            /**
             * @param Dictionary<mixed> $values
             * @param string $key
             * @return Dictionary<mixed>
             */
            function (Dictionary $values, string $key) use ($properties): Dictionary {
                $value = $this->valueForKeyPath($key);
                if ($properties->offsetExists($key)) {
                    $value = new SensitiveValue($value);
                }
                $values[$key] = $value;
                return $values;
            });
    }

    #[Override]
    public function setValuesForKeys(Dictionary $keyedValues): void
    {
        $keyedValues->forEach(fn(mixed $value, string $key) => $this->setValueForKey($value, $key));
    }

    #[Override]
    public function setNilValueForKey(string $key): void
    {
        fatal_error(sprintf("%s attribute \"%s\" cannot be null", $this->debugDescription, $key));
    }

    #[Override]
    public function mutableArrayValueForKey(string $key): ArrayClass
    {
        request_concrete_implementation($this, __FUNCTION__);
    }

    #[Override]
    public function mutableSetValueForKey(string $key): Set
    {
        request_concrete_implementation($this, __FUNCTION__);
    }

    public function associatedValueForKey(string $key): mixed
    {
        return $this->associatedValues[$key] ?? null;
    }

    public function setAssociatedValueForKey(mixed $value, string $key): void
    {
        if ($value === null) {
            if (isset($this->associatedValues[$key])) {
                unset($this->associatedValues[$key]);
            }
        } else {
            $this->associatedValues[$key] = $value;
        }
    }

    public static function staticAssociatedValueForKey(string $key): mixed
    {
        return static::$staticAssociatedValues[static::class][$key] ?? null;
    }

    public static function setStaticAssociatedValueForKey(mixed $value, string $key): void
    {
        if ($value === null) {
            if (isset(static::$staticAssociatedValues[static::class][$key])) {
                unset(static::$staticAssociatedValues[static::class][$key]);
            }
        } else {
            static::$staticAssociatedValues[static::class][$key] = $value;
        }
    }

    #[Override]
    public function jsonSerialize(): mixed
    {
        request_concrete_implementation($this, __FUNCTION__);
    }

    #[Override]
    public function __toString(): string
    {
        return $this->description;
    }
}
