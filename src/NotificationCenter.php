<?php

/**
 * Created by PhpStorm.
 * User: dante
 * Date: 16/07/20
 * Time: 10:22
 */

declare(strict_types=1);

namespace Sabatier\Foundation;

use Closure;

/**
 * A notification dispatch mechanism that enables the broadcast of information to registered observers.
 */
final class NotificationCenter
{
    private static ?NotificationCenter $default = null;
    /** @var ArrayClass<NotificationObserver> $observers */
    private ArrayClass $observers {
        get => $this->observers ??= new ArrayClass();
    }

    /**
     * The app's default notification center.
     * All system notifications sent to an app are posted to the default notification center. You can also post your own notifications there.
     * If your app uses notifications extensively, you may want to create and post to your own notification centers rather than posting only to the default notification center. When a notification is posted to a notification center, the notification center scans through the list of registered observers, which may slow down your app. By organizing notifications functionally around one or more notification centers, less work is done each time a notification is posted, which can improve performance throughout your app.
     */
    public static function default(): NotificationCenter
    {
        return self::$default ??= new NotificationCenter();
    }

    /**
     * Adds an entry to the notification center to receive notifications that passed to the provided block.
     * @param string $name The name of the notification to register for delivery to the observer block. Specify a notification name to deliver only entries with this notification name.
     * When null, the sender doesn't use notification names as criteria for delivery.
     * @param mixed $object The object that sends notifications to the observer block. Specify a sender to deliver only notifications from this sender.
     * When null, the notification center doesn't use the sender as criteria for the delivery.
     * @param Closure(Notification): void $block The block that executes when receiving a notification.
     * The notification center copies the block. The notification center strongly holds the copied block until you remove the observer registration.
     * The block takes one argument: the notification.
     * @return ObjectProtocol An opaque object to act as the observer. Notification center strongly holds this return value until you remove the observer registration.
     */
    public function addObserverForName(string $name, mixed $object, Closure $block): ObjectProtocol
    {
        $observer = new NotificationObserver($name, observed: $object, callable: $block);
        $this->observers->append($observer);
        return $observer;
    }

    /**
     * Adds an entry to the notification center to call the provided selector with the notification.
     * @param mixed $observer An object to register as an observer.
     * @param string $selector A selector that specifies the message the receiver sends `$observer` to alert it to the notification posting. The method that $selector specifies must have one and only one argument (an instance of Notification).
     * @param string $name The name of the notification to register for delivery to the observer. Specify a notification name to deliver only entries with this notification name. When null, the sender doesn't use notification names as criteria for the delivery.
     * @param mixed|null $object The object that sends notifications to the observer. Specify a notification sender to deliver only notifications from this sender. When null, the notification center doesn't use sender names as criteria for delivery.
     */
    public function addObserver(mixed $observer, string $selector, string $name, mixed $object = null): void
    {
        $this->observers->append(new NotificationObserver($name, $observer, $object, $selector));
    }

    /**
     * Removes matching entries from the notification center's dispatch table.
     * @param mixed $observer The observer to remove from the dispatch table. Specify an observer to remove only entries for this observer.
     * @param string|null $name The name of the notification to remove from the dispatch table. Specify a notification name to remove only entries with this notification name. When null, the receiver does not use notification names as criteria for removal.
     * @param mixed|null $object The sender to remove from the dispatch table. Specify a notification sender to remove only entries with this sender. When null, the receiver does not use a sender as criteria for removal.
     */
    public function removeObserver(mixed $observer, ?string $name = null, mixed $object = null): void
    {
        // A null name or object is "any", not "matches null": passing neither has to drop every entry this observer registered, and matching them literally meant nothing was ever removed unless the entry itself had been registered with a null name. Removal is also not limited to the first match — an observer registered for several names loses all of them at once. The observer is either the object passed to addObserver() or the opaque entry addObserverForName() handed back, which is the entry itself.
        $this->observers->removeAll(fn(NotificationObserver $candidate): bool => ($candidate === $observer || $candidate->observer === $observer) && ($name === null || $candidate->name === $name) && ($object === null || $candidate->observed === $object));
    }

    /**
     * Posts a given notification to the notification center.
     * @param Notification $notification The notification to post.
     */
    public function postNotification(Notification $notification): void
    {
        foreach (clone $this->observers as $observer) {
            if (($observer->name === $notification->name) && (($observer->observed && ($observer->observed === $notification->object)) || !$observer->observed)) {
                $observer->postNotification($notification);
            }
        }
    }

    /**
     * Creates a notification with a given name, sender, and information and posts it to the notification center.
     * @param string $name The name of the notification.
     * @param object|null $object The object posting the notification.
     * @param Dictionary|null $userInfo A user info dictionary with optional information about the notification.
     */
    public function postNotificationName(string $name, ?object $object = null, Dictionary|null $userInfo = null): void
    {
        if (!$this->observers->isEmpty) {
            $this->postNotification(new Notification($name, $object, $userInfo));
        }
    }
}
