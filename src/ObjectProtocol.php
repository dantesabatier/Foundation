<?php

/**
 * Created by PhpStorm.
 * User: dante
 * Date: 28/06/20
 * Time: 19:04
 */

namespace Sabatier\Foundation;

/**
 * Interface ObjectInterface
 * The group of methods that are fundamental to all Foundation objects.
 * @package Sabatier\Foundation
 */
interface ObjectProtocol extends CustomDebugStringConvertible, Equatable
{
    /**
     * Returns the class object for the receiver's superclass.
     * @return class-string
     */
    public function superclass(): string;

    /**
     * Returns a Boolean value that indicates whether the receiver is an instance of given class or an instance of any class that inherits from that class.
     * @param class-string $class A class to be tested.
     * @return bool true if the receiver is an instance of class or an instance of any class that inherits from class, otherwise false.
     */
    public function isKind(string $class): bool;

    /**
     * Returns a Boolean value that indicates whether the receiver is an instance of a given class.
     * @param class-string $class A class to be tested.
     * @return bool true if the receiver is an instance of class, otherwise false.
     */
    public function isMember(string $class): bool;

    /**
     * Returns a Boolean value that indicates whether the receiving class is a subclass of, or identical to, a given class.
     * @param class-string $class
     * @return bool true if the receiver is a subclass of class, otherwise false.
     */
    public function isSubclass(string $class): bool;

    /**
     * Returns an integer that can be used as a table address in a hash table structure.
     * @return int
     */
    public function hash(): int;

    /**
     * Returns a Boolean value that indicates whether the receiver implements or inherits a method that can respond to a specified message.
     * @param string $selector A selector that identifies a message.
     * @return bool true if instances of the receiver are capable of responding to selector messages, otherwise false.
     */
    public function responds(string $selector): bool;

    /**
     * Returns a Boolean value that indicates whether the receiver conforms to a given protocol.
     * @param class-string $protocol A protocol object that represents a particular protocol.
     * @return boolean true if the receiver conforms to aProtocol, otherwise false.
     */
    public function conforms(string $protocol): bool;

    /**
     * Returns a Boolean value that indicates whether instances of the receiver are capable of responding to a given selector.
     * @param string $selector A selector that identifies a message.
     * @return bool true if instances of the receiver are capable of responding to selector messages, otherwise false.
     */
    public static function instancesRespond(string $selector): bool;

    /**
     * Sends a message to the receiver with an object as the argument.
     * @param string $selector A selector identifying the message to send.
     * @param array $arguments Arguments of the message.
     * @return mixed An object that is the result of the message.
     */
    public function performSelector(string $selector, array $arguments = []): mixed;
}
