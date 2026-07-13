<?php

declare(strict_types=1);

namespace Sabatier\Foundation;

/**
 * A message rendered as an object.
 */
final class Invocation
{
    /** @var string The receiver's selector, or 0 if it hasn't been set. */
    public string $selector = "0";
    /** @var object|null The receiver's target, or null if the receiver has no target. The target is the receiver of the message sent by {@see invoke()}. */
    public ?object $target = null;
    /** @var ArrayClass<mixed> */
    public ArrayClass $arguments {
        get => $this->arguments ??= new ArrayClass();
    }
    public mixed $returnValue;

    /**
     * Sends the receiver's message (with arguments) to its target and sets the return value.
     *
     * You must set the receiver's target, selector, and argument values before calling this method.
     */
    public function invoke(): void
    {
        $target = $this->target ?? fatal_error("Invalid argument: target cannot be null");
        $selector = $this->selector;
        method_exists($target, $selector) ?: fatal_error(sprintf("<%s %s> %s() unrecognized selector sent to instance", class_name($target::class), spl_object_id($target), $selector));
        $this->returnValue = $target->$selector(...$this->arguments->array);
    }

    /**
     * Sets the receiver's target, sends the receiver's message (with arguments) to that target, and sets the return value.
     *
     * You must set the receiver's selector and argument values before calling this method.
     * @param object $target The object to set as the receiver's target.
     */
    public function invokeWithTarget(object $target): void
    {
        $this->target = $target;
        $this->invoke();
    }
}
