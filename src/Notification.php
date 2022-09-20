<?php
/**
 * Created by PhpStorm.
 * User: dante
 * Date: 16/07/20
 * Time: 10:17
 */

namespace Sabatier\Foundation;

/**
 * Class Notification
 * A container for information broadcast through a notification center to all registered observers.
 * @package Sabatier\Foundation
 */
class Notification implements CustomStringConvertible
{
    /**
     * Initializes a new notification.
     * The default value for userInfo is nil.
     * @param string $name The name for the new notification. May not be nil.
     * @param mixed $object The object for the new notification.
     * @param Dictionary<mixed>|null $userInfo The user information dictionary for the new notification. May be nil.
     */
    public function __construct(public readonly string $name, public readonly mixed $object = null, public readonly ?Dictionary $userInfo = null)
    {
    }

    public function __toString()
    {
        return $this->description();
    }

    public function description(): string
    {
        return sprintf("name = %s object = %s userInfo = %s", $this->name, human_readable_value($this->object), human_readable_value($this->userInfo));
    }
}
