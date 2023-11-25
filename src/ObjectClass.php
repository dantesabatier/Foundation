<?php

/**
 * Created by PhpStorm.
 * User: dante
 * Date: 13/06/20
 * Time: 18:42
 */

namespace Sabatier\Foundation;

use Closure;
use JetBrains\PhpStorm\ExpectedValues;
use JetBrains\PhpStorm\Pure;
use JsonSerializable;

/**
 * The root class of most class hierarchies, from which subclasses inherit a basic interface to the runtime system and the ability to behave as Objective-C objects.
 */
class ObjectClass implements ObjectProtocol, KeyValueObserving, KeyValueCoding, Comparable, JsonSerializable
{
    use Debuggable;

    /** @var KeyValueObservance[] $observances */
    private array $observances = [];
    /** @var array<string, mixed> */
    private static array $staticAssociatedValues = [];
    /** @var array<string, mixed> */
    private array $associatedValues = [];

    /**
     * Initializes the class before it receives its first message.
     */
    public static function initialize(): void
    {
    }

    #[Pure]
    final public function superclass(): string
    {
        return get_parent_class($this);
    }

    #[Pure]
    final public function isKind(string $class): bool
    {
        return is_a($this, $class, true);
    }

    #[Pure]
    final public function isMember(string $class): bool
    {
        return $this->isKind($class);
    }

    #[Pure]
    final public function isSubclass(string $class): bool
    {
        return is_subclass_of($this, $class);
    }

    final public function hash(): int
    {
        return spl_object_id($this);
    }

    public function responds(string $selector): bool
    {
        return method_exists($this, $selector);
    }

    final public function conforms(string $protocol): bool
    {
        return isset(class_implements($this)[$protocol]);
    }

    #[Pure]
    public static function instancesRespond(string $selector): bool
    {
        return method_exists(static::class, $selector);
    }

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
     * The runtime system invokes this method whenever an object receives an aSelector message it can't respond to or forward. This method, in turn, raises an InvalidArgumentException, and generates an error message.
     * @param string $selector A Selector that identifies a method not implemented or recognized by the receiver.
     */
    public function doesNotRecognizeSelector(string $selector): never
    {
        fatal_error(sprintf("%s %s() unrecognized selector sent to instance", $this->debugDescription(), $selector));
    }

    /**
     * Overridden by subclasses to forward messages to other objects.
     * @param Invocation $invocation The invocation to forward.
     */
    public function forwardInvocation(Invocation $invocation): void
    {
        $this->doesNotRecognizeSelector($invocation->selector);
    }

    public function compare(mixed $other): ComparisonResult
    {
        request_concrete_implementation($this, __FUNCTION__);
    }

    public function isEqual(mixed $other): bool
    {
        if ($other instanceof ObjectClass) {
            return $this->hash() === $other->hash();
        }
        return false;
    }

    public function observe(string $keyPath, #[ExpectedValues(flagsFromClass: KeyValueObservingOptions::class)] int $options = KeyValueObservingOptions::new, Closure $handler = null): KeyValueObservation
    {
        $observation = new KeyValueObservation($this, $keyPath);
        $this->observances[] = new KeyValueObservance($observation, $keyPath, $options, handler: $handler);
        return $observation;
    }

    public function observeValue(string $keyPath, mixed $object, KeyValueObservedChange $change, mixed $context = null): void
    {
    }

    public function addObserver(object $observer, string $keyPath, #[ExpectedValues(flagsFromClass: KeyValueObservingOptions::class)] int $options = KeyValueObservingOptions::new, mixed $context = null): void
    {
        if (static::automaticallyNotifiesObserversForKey($keyPath)) {
            $this->observances[] = new KeyValueObservance($observer, $keyPath, $options, $context);
        }
    }

    public function removeObserver(object $observer, string $keyPath, mixed $context = null): void
    {
        if ($observance = array_first($this->observances, fn(KeyValueObservance $observance): bool => $observance->observer === $observer && $observance->keyPath === $keyPath)) {
            array_remove($this->observances, $observance);
        }
    }

    public function willChangeValueForKey(string $key, KeyValueChange $changeKind = KeyValueChange::setting, mixed $changedValue = null): void
    {
        foreach ($this->observances as $observance) {
            $keyPath = $observance->keyPath;
            if ($keyPath === $key) {
                $change = new KeyValueObservedChange();
                $change->kind = $changeKind;
                if ($observance->options & KeyValueObservingOptions::new) {
                    $change->newValue = $changedValue;
                }
                if ($observance->options & KeyValueObservingOptions::old && $changeKind !== KeyValueChange::setting) {
                    $change->oldValue = $this->valueForKey($key);
                }
                if ($observance->options & KeyValueObservingOptions::prior) {
                    $change->isPrior = true;
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
    }

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
                    $change->oldValue = $changedValue;
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
    }

    public static function keyPathsForValuesAffectingValueForKey(string $key): Set
    {
        $selector = "keyPathsForValuesAffectingValueFor" . ucfirst($key);
        if (static::instancesRespond($selector)) {
            return static::$selector();
        }
        return new Set();
    }

    public static function automaticallyNotifiesObserversForKey(string $key): bool
    {
        $selector = "automaticallyNotifiesObserversFor" . ucfirst($key);
        if (static::instancesRespond($selector)) {
            return static::$selector();
        }
        return true;
    }

    public function validateValueForKey(mixed &$value, string $key): bool
    {
        $selector = "validate" . ucfirst($key);
        if ($this->responds($selector)) {
            return $this->perform($selector, [&$value]);
        }
        return true;
    }

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
            throw new UndefinedKeyException(sprintf("%s %s() \"%s\" is not key value coding compliant for the key \"%s\"", $this->debugDescription(), __FUNCTION__, typeof($value), $keyPath));
        }
        return $obj->validateValueForKeyPath($value, $keyPath);
    }

    public function valueForUndefinedKey(string $key): mixed
    {
        throw new UndefinedKeyException(sprintf("%s is not key value coding compliant for the key \"%s\"", $this->debugDescription(), $key));
    }

    public function setValueForUndefinedKey(mixed $value, string $key): void
    {
        throw new UndefinedKeyException(sprintf("%s is not key value coding compliant for the key \"%s\"", $this->debugDescription(), $key));
    }

    public function valueForKey(string $key): mixed
    {
        if (property_exists($this, $key)) {
            return $this->$key;
        }
        return $this->valueForUndefinedKey($key);
    }

    public function setValueForKey(mixed $value, string $key): void
    {
        if (!$this->validateValueForKey($value, $key)) {
            return;
        }
        if (property_exists($this, $key)) {
            $this->willChangeValueForKey($key, KeyValueChange::replacement, $value);
            $this->$key = $value;
            $this->didChangeValueForKey($key, KeyValueChange::replacement, $value);
            return;
        }
        $this->setValueForUndefinedKey($value, $key);
    }

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
            throw new UndefinedKeyException(sprintf("%s %s() \"%s\" is not key value coding compliant for the key \"%s\"", $this->debugDescription(), __FUNCTION__, typeof($obj), $remainderPath));
        }
        return $obj->valueForKeyPath($remainderPath);
    }

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
            throw new UndefinedKeyException(sprintf("%s %s() \"%s\" is not key value coding compliant for the key \"%s\"", $this->debugDescription(), __FUNCTION__, typeof($value), $keyPath));
        }
        $obj->setValueForKeyPath($value, $keyPath);
    }

    public function dictionaryWithValues(ArrayClass $keys): Dictionary
    {
        $values = new Dictionary();
        foreach ($keys as $key) {
            $values->setValueForKey($this->valueForKey($key), $key);
        }
        return $values;
    }

    public function setValuesForKeys(Dictionary $keyedValues): void
    {
        foreach ($keyedValues as $key => $value) {
            $this->setValueForKeyPath($value, $key);
        }
    }

    public function setNilValueForKey(string $key): void
    {
        fatal_error(sprintf("%s attribute \"%s\" cannot be null", $this->debugDescription(), $key));
    }

    public function mutableArrayValueForKey(string $key): ArrayClass
    {
        request_concrete_implementation($this, __FUNCTION__);
    }

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

    public function jsonSerialize(): mixed
    {
        request_concrete_implementation($this, __FUNCTION__);
    }

    public function __toString(): string
    {
        return $this->description();
    }
}
