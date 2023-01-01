<?php

namespace Sabatier\Foundation;

/**
 * A general-purpose recorder of operations that enables undo and redo.
 * @property-read int $levelsOfUndo The maximum number of top-level undo groups the receiver holds. An integer specifying the number of undo groups. A limit of 0 indicates no limit, so old undo groups are never dropped. When ending an undo group results in the number of groups exceeding this limit, the oldest groups are dropped from the stack. The default is 0. If you change the limit to a level below the prior limit, old undo groups are immediately dropped.
 * @property-read bool $canUndo A Boolean value that indicates whether the receiver has any actions to undo.
 * @property-read bool $canRedo A Boolean value that indicates whether the receiver has any actions to redo.
 * @property-read int $groupingLevel The number of nested undo groups (or redo groups, if Redo was invoked last) in the current event loop. An integer indicating the number of nested groups. If 0 is returned, there is no open undo or redo group.
 * @property-read bool $isUndoRegistrationEnabled A Boolean value that indicates whether the recording of undo operations is enabled.
 * @property-read bool $isUndoing Returns a Boolean value that indicates whether the receiver is in the process of performing its {@see undo()} or {@see undoNestedGroup()} method.
 * @property-read bool $isRedoing Returns a Boolean value that indicates whether the receiver is in the process of performing its {@see redo()} method.
 * @property-read string $undoActionName The name identifying the undo action.
 * @property-read string $redoActionName The name identifying the redo action.
 * @property-read string $undoMenuItemTitle The complete title of the Undo menu command, for example, “Undo Paste.”
 * @property-read string $redoMenuItemTitle The complete title of the Redo menu command, for example, “Redo Paste.”
 * @property-read bool $undoActionIsDiscardable Boolean value that indicates whether the next undo action is discardable.
 * @property-read bool $redoActionIsDiscardable Boolean value that indicates whether the next redo action is discardable.
 */
class UndoManager extends ObjectClass
{
    protected int $levelsOfUndo = 0;
    /** @var bool A Boolean value that indicates whether the receiver automatically creates undo groups around each pass of the run loop. true if the receiver automatically creates undo groups around each pass of the run loop, otherwise false. The default is true. If you turn automatic grouping off, you must close groups explicitly before invoking either {@see undo()} or {@see undoNestedGroup()}. */
    public bool $groupsByEvent = true;
    protected int $groupingLevel = 0;
    protected bool $isUndoRegistrationEnabled = true;
    protected bool $isUndoing = false;
    protected bool $isRedoing = false;
    protected bool $undoActionIsDiscardable = false;
    protected bool $redoActionIsDiscardable = false;
    /** @var ArrayClass<UndoGroup> */
    private readonly ArrayClass $undoStack;
    /** @var ArrayClass<UndoGroup> */
    private readonly ArrayClass $redoStack;
    private mixed $nextTarget = null;
    private ?UndoGroup $group = null;

    public function __construct()
    {
        $this->undoStack = new ArrayClass();
        $this->redoStack = new ArrayClass();
    }

    public function __get(string $name)
    {
        return match ($name) {
            "canUndo" => !$this->undoStack->isEmpty(),
            "canRedo" => !$this->redoStack->isEmpty(),
            "redoActionName" => $this->canRedo ? $this->redoStack->last()?->actionName : null,
            "undoActionName" => $this->canUndo ? $this->undoStack->last()?->actionName : null,
            "redoMenuItemTitle" => $this->redoMenuTitle($this->redoActionName),
            "undoMenuItemTitle" => $this->undoMenuTitle($this->undoActionName),
            "levelsOfUndo", "groupingLevel", "isUndoRegistrationEnabled", "isUndoing", "isRedoing", "undoActionIsDiscardable", "redoActionIsDiscardable" => $this->$name,
            default => $this->valueForUndefinedKey($name)
        };
    }

    public function __set(string $name, mixed $value): void
    {
        if ($name === "levelsOfUndo") {
            $this->$name = $value;
            while ($this->undoStack->count() > $value) {
                $this->undoStack->removeAt(0);
            }
            while ($this->redoStack->count() > $value) {
                $this->redoStack->removeAt(0);
            }
        } else {
            $this->setValueForUndefinedKey($value, $name);
        }
    }

    private function begin(): void
    {
        $parent = $this->group;
        $this->group = new UndoGroup($parent);
        if (!$this->isUndoing && !$this->isRedoing) {
            NotificationCenter::default()->postNotificationName(UndoManagerDidOpenUndoGroupNotification, $this);
        }
    }

    /**
     * Registers the selector of the specified target to implement a single undo operation that the target receives.
     * @param mixed $target The target of the undo operation.
     * @param string $selector The selector for the undo operation.
     * @param mixed $object The argument sent with the selector.
     */
    public function registerUndo(mixed $target, string $selector, mixed $object): void
    {
        if (!$this->isUndoRegistrationEnabled) {
            return;
        }
        if (!$this->group instanceof UndoGroup) {
            if (!$this->groupsByEvent) {
                throw new InternalInconsistencyException("registerUndo() without beginUndoGrouping()");
            }
            $this->begin();
        }
        /** @var UndoGroup $g */
        $g = $this->group;
        $invocation = new Invocation();
        $invocation->target = $target;
        $invocation->selector = $selector;
        $invocation->arguments->append($object);
        $g->addInvocation($invocation);
        if (!$this->isUndoing && !$this->isRedoing) {
            $this->redoStack->removeAll();
        }
    }

    /**
     * Prepares the undo manager for invocation-based undo with the given target as the subject of the next undo operation.
     * @param mixed $target The target of the undo operation.
     * @return UndoManager A proxy object that forwards messages to the undo manager for recording as undo actions.
     */
    public function prepare(mixed $target): UndoManager
    {
        $this->nextTarget = $target;
        return $this;
    }

    /**
     * Closes the top-level undo group if necessary and invokes {@see undoNestedGroup()}.
     *
     * This method also invokes {@see endUndoGrouping()} if the nesting level is 1. Raises an InternalInconsistencyException if more than one undo group is open (that is, if the last group isn't at the top level).
     * This method posts an UndoManagerCheckpointNotification.
     */
    public function undo(): void
    {
        if ($this->groupingLevel === 1) {
            $this->endUndoGrouping();
        }
        if ($this->group !== null) {
            throw new InternalInconsistencyException("Undo with nested groups");
        }
        $this->undoNestedGroup();
    }

    /**
     * Performs the undo operations in the last undo group (whether top-level or nested), recording the operations on the redo stack as a single group.
     *
     * Raises an InternalInconsistencyException if any undo operations have been registered since the last {@see enableUndoRegistration()} message.
     * This method posts an {@see UndoManagerCheckpointNotification} and {@see UndoManagerWillUndoChangeNotification} before it performs the undo operation, and it posts an {@see UndoManagerDidUndoChangeNotification} after it performs the undo operation.
     */
    public function undoNestedGroup(): void
    {
        NotificationCenter::default()->postNotificationName(UndoManagerCheckpointNotification, $this);
        if ($this->isUndoing || $this->isRedoing) {
            throw new InternalInconsistencyException("undoNestedGroup() while undoing or redoing");
        }
        if ($this->undoStack->isEmpty()) {
            return;
        }
        NotificationCenter::default()->postNotificationName(UndoManagerWillUndoChangeNotification, $this);
        $oldGroup = $this->group;
        $this->group = null;
        if ($oldGroup !== null) {
            $groupToUndo = $oldGroup;
            $oldGroup = $groupToUndo->parent;
            $groupToUndo->parent = null;
            $this->redoStack->append($groupToUndo);
        } else {
            $groupToUndo = $this->undoStack->popLast();
        }
        assert($groupToUndo instanceof UndoGroup);
        $this->begin();
        $groupToUndo->perform();
        $this->endUndoGrouping();
        $this->isUndoing = false;
        $this->group = $oldGroup;
        if ($e = $this->redoStack->last()) {
            $e->actionName = $groupToUndo->actionName;
        }
        NotificationCenter::default()->postNotificationName(UndoManagerDidUndoChangeNotification, $this);
    }

    /**
     * Performs the operations in the last group on the redo stack, if there are any, recording them on the undo stack as a single group.
     *
     * Raises an InternalInconsistencyException if the method is invoked during an undo operation.
     * This method posts an {@see UndoManagerCheckpointNotification} and {@see UndoManagerWillRedoChangeNotification} before it performs the redo operation, and it posts the {@see UndoManagerDidRedoChangeNotification} after it performs the redo operation.
     */
    public function redo(): void
    {
        if ($this->isUndoing || $this->isRedoing) {
            throw new InternalInconsistencyException("Redo while undoing or redoing");
        }
        NotificationCenter::default()->postNotificationName(UndoManagerCheckpointNotification, $this);
        if (!($group = $this->redoStack->popLast())) {
            return;
        }
        $oldGroup = $this->group;
        $this->group = null;
        $this->isRedoing = true;
        $this->begin();
        $group->perform();
        $this->endUndoGrouping();
        $this->isRedoing = false;
        $this->group = $oldGroup;
        if ($e = $this->undoStack->last()) {
            $e->actionName = $group->actionName;
        }
        NotificationCenter::default()->postNotificationName(UndoManagerDidRedoChangeNotification, $this);
    }

    /**
     * Marks the beginning of an undo group.
     *
     * All individual undo operations before a subsequent {@see endUndoGrouping()} message are grouped together and reversed by a later {@see undo()} message. By default, undo groups are begun automatically at the start of the event loop, but you can begin your own undo groups with this method, and nest them within other groups. This method posts an {@see UndoManagerCheckpointNotification} unless a top-level undo is in progress. It posts an {@see UndoManagerDidOpenUndoGroupNotification} if a new group was successfully created.
     */
    public function beginUndoGrouping(): void
    {
        if ($this->group === null && $this->groupsByEvent) {
            $this->begin();
        }
        NotificationCenter::default()->postNotificationName(UndoManagerCheckpointNotification, $this);
        $this->begin();
    }

    /**
     * Marks the end of an undo group.
     *
     * All individual undo operations back to the matching {@see beginUndoGrouping()} message are grouped together and reversed by a later undo or {@see undoNestedGroup()} message. Undo groups can be nested, thus providing functionality similar to nested transactions. Raises an InternalInconsistencyException if there's no beginUndoGrouping message in effect. This method posts an {@see UndoManagerCheckpointNotification} and an {@see UndoManagerDidCloseUndoGroupNotification} just before the group is closed.
     */
    public function endUndoGrouping(): void
    {
        $group = $this->group;
        if (!$group instanceof UndoGroup) {
            throw new InternalInconsistencyException("endUndoGrouping() without beginUndoGrouping()");
        }
        NotificationCenter::default()->postNotificationName(UndoManagerCheckpointNotification, $this);
        if (!$this->isUndoing && !$this->isRedoing) {
            NotificationCenter::default()->postNotificationName(UndoManagerWillCloseUndoGroupNotification, $this);
        }
        NotificationCenter::default()->postNotificationName(UndoManagerDidCloseUndoGroupNotification, $this);
        $parent = $group->parent;
        $this->group = $parent;
        $group->parent = null;
        if (!$parent instanceof UndoGroup) {
            if ($this->isUndoing) {
                if ($this->levelsOfUndo === $this->redoStack->count() && !$group->actions->isEmpty()) {
                    $this->redoStack->removeAt(0);
                }
                if (!$group->actions->isEmpty()) {
                    $this->redoStack->append($group);
                }
            } else {
                if ($this->levelsOfUndo === $this->undoStack->count() && !$group->actions->isEmpty()) {
                    $this->undoStack->removeAt(0);
                }
                if (!$group->actions->isEmpty()) {
                    $this->undoStack->append($group);
                }
            }
        } else {
            foreach ($group->actions as $action) {
                $parent->addInvocation($action);
            }
        }
    }

    /**
     * Disables the recording of undo operations, whether by {@see registerUndo()} or by invocation-based undo.
     *
     * This method can be invoked multiple times by multiple clients. The {@see enableUndoRegistration()} method must be invoked an equal number of times to re-enable undo registration.
     */
    public function disableUndoRegistration(): void
    {
        $this->isUndoRegistrationEnabled = false;
    }

    /**
     * Enables the recording of undo operations.
     *
     * Because undo registration is enabled by default, it is often used to balance a prior {@see disableUndoRegistration()} message. Undo registration isn't actually re-enabled until an enable message balances the last disable message in effect. Raises an InternalInconsistencyException if invoked while no {@see disableUndoRegistration()} message is in effect.
     */
    public function enableUndoRegistration(): void
    {
        if ($this->isUndoRegistrationEnabled) {
            throw new InternalInconsistencyException();
        }
        $this->isUndoRegistrationEnabled = true;
    }

    /**
     * Clears the undo and redo stacks of all operations involving the specified target as the recipient of the undo message.
     *
     * Doesn't re-enable the receiver if it's disabled.
     * @param object|null $target The recipient of the undo messages to be removed.
     */
    public function removeAllActions(?object $target): void
    {
        $redoStack = $this->redoStack;
        $undoStack = $this->undoStack;
        if ($target === null) {
            if ($this->group !== null) {
                $this->endUndoGrouping();
            }
            $redoStack->removeAll();
            $undoStack->removeAll();
        }
        $i = $redoStack->endIndex();
        while ($i-- > 0) {
            $g = $redoStack[$i];
            if (!$g->removeActions($target)) {
                $redoStack->removeAt($i);
            }
        }
        $i = $undoStack->endIndex();
        while ($i-- > 0) {
            $g = $undoStack[$i];
            if (!$g->removeActions($target)) {
                $undoStack->removeAt($i);
            }
        }
        $this->isUndoing = false;
        $this->isRedoing = false;
        $this->isUndoRegistrationEnabled = true;
    }

    /**
     * Sets the name of the action associated with the Undo or Redo command.
     *
     * If actionName is an empty string, the action name currently associated with the menu command is removed. There is no effect if actionName is nil.
     * @param string $actionName The name of the action.
     */
    public function setActionName(string $actionName): void
    {
        if ($group = $this->group) {
            $group->actionName = $actionName;
        }
    }

    /**
     * Returns the complete, localized title of the Undo menu command for the action identified by the given name.
     *
     * Override this method if you want to customize the localization behavior. This method is invoked by {@see undoMenuItemTitle}.
     * @param string $actionName The name of the undo action.
     * @return string The localized title of the undo menu item.
     */
    public function undoMenuTitle(string $actionName): string
    {
        $name = localized_string("Redo");
        if ($actionName === "") {
            return $name;
        }
        return sprintf("%s %s", $name, $actionName);
    }

    /**
     * Returns the complete, localized title of the Redo menu command for the action identified by the given name.
     *
     * Override this method if you want to customize the localization behavior. This method is invoked by {@see redoMenuItemTitle}.
     * @param string $actionName The name of the redo action.
     * @return string The localized title of the redo menu item.
     */
    public function redoMenuTitle(string $actionName): string
    {
        $name = localized_string("Undo");
        if ($actionName === "") {
            return $name;
        }
        return sprintf("%s %s", $name, $actionName);
    }

    /**
     * Sets whether the next undo or redo action is discardable.
     * @param bool $discardable Specifies if the action is discardable. true if the next undo or redo action can be discarded; false otherwise.
     */
    public function setActionIsDiscardable(bool $discardable): void
    {
    }
}
