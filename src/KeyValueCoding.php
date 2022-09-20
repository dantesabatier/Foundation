<?php

/**
 * Created by PhpStorm.
 * User: dante
 * Date: 13/06/20
 * Time: 19:06
 */

namespace Sabatier\Foundation;

/**
 * Interface KeyValueCoding
 * A mechanism by which you can access the properties of an object indirectly by name or key.
 * The basic methods for accessing an object's values are {@see setValueForKey()}, which sets the value for the property identified by the specified key, and {@see valueForKey()}, which returns the value for the property identified by the specified key. Thus, all of an object's properties can be accessed in a consistent manner.
 * The default implementation relies on the accessor methods normally implemented by objects (or to access instance variables directly if need be).
 * @package Sabatier\Foundation
 * @template T
 */
interface KeyValueCoding
{
    /**
     * Returns the value for the property identified by a given key.
     * @param string $key The name of one of the receiver's properties.
     * @return T|null The value for the property identified by key.
     */
    public function valueForKey(string $key);

    /**
     * Returns the value for the derived property identified by a given key path.
     * The default implementation gets the destination object for each relationship using {@see valueForKey()} and returns the result of a {@see valueForKey()} message to the final object.
     * @param string $keyPath A key path of the form relationship.property (with one or more relationships);
     * for example “department.name” or “department.manager.lastName”.
     * @return T|null The value for the derived property identified by keyPath.
     */
    public function valueForKeyPath(string $keyPath);

    /**
     * Returns a dictionary containing the property values identified by each of the keys in a given array.
     * The default implementation invokes {@see valueForKey()} for each key in keys.
     * @param ArrayClass<string> $keys An array containing string objects that identify properties of the receiver.
     * @return Dictionary<mixed> A dictionary containing as keys the property names in keys,
     * with corresponding values being the corresponding property values.
     */
    public function dictionaryWithValues(ArrayClass $keys): Dictionary;

    /**
     * Invoked by {@see valueForKey()} when it finds no property corresponding to a given key.
     * Subclasses can override this method to return an alternate value for undefined keys.
     * The default implementation raises an {@see UndefinedKeyException}.
     * @param string $key The name of one of the receiver's properties.
     * @return T|null The value for the property identified by key.
     */
    public function valueForUndefinedKey(string $key);

    /**
     * Returns a mutable array proxy that provides read-write access to an ordered to-many relationship specified by a given key.
     * Objects added to the mutable array become related to the receiver, and objects removed from the mutable array become unrelated. The default implementation recognizes the same simple accessor methods and array accessor methods as {@see valueForKey()}, and follows the same direct instance variable access policies, but always returns a mutable collection proxy object instead of the immutable collection that {@see valueForKey()} would return.
     * @param string $key The name of an ordered to-many relationship.
     * @return ArrayClass<mixed> A mutable array proxy that provides read-write access to the ordered to-many relationship specified by key.
     */
    public function mutableArrayValueForKey(string $key): ArrayClass;

    /**
     * Returns a mutable set proxy that provides read-write access to the unordered to-many relationship specified by a given key.
     * Objects added to the mutable set proxy become related to the receiver, and objects removed from the mutable set become unrelated.
     * The default implementation recognizes the same simple accessor methods and set accessor methods as {@see valueForKey()}, and follows the same direct instance variable access policies.
     * The default implementation raises an exception if relationship key cannot be found.
     * @param string $key The name of an unordered to-many relationship.
     * @return Set<mixed> A mutable set that provides read-write access to the unordered to-many relationship specified by key.
     */
    public function mutableSetValueForKey(string $key): Set;

    /**
     * Sets the value for the property identified by a given key path to a given value.
     * The default implementation of this method gets the destination object for each relationship using
     * {@see valueForKey()}, and sends the final object a {@see setValueForKey()} message.
     * @param mixed|null $value The value for the property identified by keyPath.
     * @param string $keyPath A key path of the form relationship.property (with one or more relationships): for example “department.name” or “department.manager.lastName.”
     */
    public function setValueForKeyPath(mixed $value, string $keyPath): void;

    /**
     * Sets properties of the receiver with values from a given dictionary, using its keys to identify the properties.
     * The default implementation invokes {@see setValueForKey()} for each key-value pair.
     * @param Dictionary<mixed> $keyedValues
     */
    public function setValuesForKeys(Dictionary $keyedValues): void;

    /**
     * Invoked by {@see setValueForKey()} when it's given a nil value for a scalar value (such as an int or float).
     * Subclasses can override this method to handle the request in some other way, such as by substituting 0
     * or a sentinel value for nil and invoking {@see setValueForKey()} again or setting the variable directly.
     * The default implementation raises an {@see InvalidArgumentException}.
     * @param string $key The name of one of the receiver's properties.
     */
    public function setNilValueForKey(string $key): void;

    /**
     * Sets the property of the receiver specified by a given key to a given value.
     * @param T|null $value
     * @param string $key
     */
    public function setValueForKey(mixed $value, string $key): void;

    /**
     * Invoked by {@see setValueForKey()} when it finds no property for a given key.
     * Subclasses can override this method to handle the request in some other way.
     * The default implementation raises an {@see UndefinedKeyException}.
     * @param T|null $value The value for the key identified by key.
     * @param string $key
     */
    public function setValueForUndefinedKey(mixed $value, string $key): void;

    /**
     * Throws an error when the value specified by a given pointer is not valid or can't be made valid for the property identified by a given key.
     * The default implementation of this function searches the class of the receiver for a property specific validation function with a particular signature, allowing that function to determine the outcome of the validation.
     * For it to be found, the property specific validation function must be exposed, must be named according to the pattern validate<InKey>, must take a single, optional AnyObject pointer argument, and must throw.
     * For example, for a property named someString, the validation function is:
     * <code>
     * public function validateSomeString(&$value) {
     *     // Test, and possibly replace the value here; or throw an error
     * }
     * </code>
     * If you define such a function, the default implementation of {@see validateValueForKey()} calls it when asked to validate the corresponding property, allowing your function to either alter the input value or throw an error.
     * If no such function exists for a particular property, {@see validateValueForKey()} returns without taking any other action. In other words, by default, the general validation call succeeds if you don't explicitly provide a validation function for the given property.
     * @param T|null $value A pointer to a new value for the property identified by key.
     * This method may modify or replace the value in order to make it valid.
     * @param string $key The name of one of the receiver's properties. The key must specify an attribute or a to-one relationship.
     * @return bool A Boolean set to true if the value pointed at by ioValue is valid for the property identified by inKey, or if the method is able to modify the value at ioValue to make it valid; otherwise false.
     */
    public function validateValueForKey(mixed &$value, string $key): bool;

    /**
     * Throws an error when the value specified by a given pointer is not valid for a given key path relative to the receiver.
     * The default implementation of this function gets the destination instance for each relationship using
     * {@see valueForKey()} and then calls the {@see validateValueForKey()} function for the property.
     * The outcome of that call determines whether this one throws an error or not.
     * @param T|null $value A pointer to a new value for the property identified by inKeyPath.
     * This method may modify or replace the value in order to make it valid.
     * @param string $keyPath The name of one of the receiver's properties.
     * The key path must specify an attribute or a to-one relationship. The key path has the form relationship.property (with one or more relationships); for example department.name or department.manager.lastName.
     * @return bool A Boolean that is true if the value pointed at by ioValue is valid for the property identified by inKeyPath, or if the method is able to modify the value at ioValue to make it valid; otherwise false.
     */
    public function validateValueForKeyPath(mixed &$value, string $keyPath): bool;
}
