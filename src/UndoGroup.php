<?php

namespace Sabatier\Foundation;

/** @internal */
class UndoGroup
{
    /** @var ArrayClass<Invocation> */
    public readonly ArrayClass $actions;
    public string $actionName = "";

    public function __construct(public ?UndoGroup $parent = null)
    {
        $this->actions = new ArrayClass();
    }

    public function addInvocation(Invocation $invocation): void
    {
        $this->actions->append($invocation);
    }

    public function perform(): void
    {
        foreach ($this->actions as $action) {
            $action->invoke();
        }
    }

    public function removeActions(?object $target): bool
    {
        $this->actions->removeAll(fn(Invocation $invocation): bool => is_equal($invocation->target, $target));
        return !$this->actions->isEmpty;
    }
}
