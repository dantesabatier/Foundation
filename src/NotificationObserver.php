<?php

/**
 * Created by PhpStorm.
 * User: dante
 * Date: 16/07/20
 * Time: 10:37
 */

namespace Sabatier\Foundation;

/** @internal */
class NotificationObserver extends ObjectClass
{
    public function __construct(public readonly string $name, public readonly ?object $observer = null, public readonly ?object $observed = null, public readonly mixed $callable = null)
    {
    }

    public function postNotification(Notification $notification): void
    {
        $callable = $this->callable ?? fatal_error();
        if (is_callable($callable)) {
            $callable($notification);
            return;
        }
        $observer = $this->observer ?? fatal_error();
        if (!method_exists($observer, $callable)) {
            fatal_error(sprintf("%s %s() unrecognized selector sent to instance", human_readable_value($observer), $callable));
        }
        $observer->$callable($notification);
    }
}
