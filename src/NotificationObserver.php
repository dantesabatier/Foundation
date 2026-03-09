<?php

/**
 * Created by PhpStorm.
 * User: dante
 * Date: 16/07/20
 * Time: 10:37
 */

namespace Sabatier\Foundation;

/** @internal */
final class NotificationObserver extends ObjectClass
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
        method_exists($observer, $callable) ?: $observer
                |> human_readable_value(...)
                |> (fn(string $x): string => sprintf("%s %s() unrecognized selector sent to instance", $x, $callable))
                |> fatal_error(...);
        $observer->$callable($notification);
    }
}
