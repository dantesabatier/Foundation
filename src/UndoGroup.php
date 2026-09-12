<?php

declare(strict_types=1);

namespace Sabatier\Foundation;

/** @internal */
final class UndoGroup
{
    /** @var ArrayClass<Invocation> */
    private(set) ArrayClass $actions;
    public string $actionName = "";

    /** @param UndoGroup|null $parent The enclosing group, or null for a top-level group. */
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
        $this->actions->reversed()->forEach(fn(Invocation $action) => $action->invoke());
    }

    public function removeActions(?object $target): bool
    {
        $this->actions->removeAll(fn(Invocation $invocation): bool => is_equal($invocation->target, $target));
        return !$this->actions->isEmpty;
    }
}
