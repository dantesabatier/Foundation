<?php

declare(strict_types=1);

namespace Sabatier\Foundation;

use Override;

/**
 * A general-purpose recorder of operations that enables undo and redo.
 */
final class UndoManager extends ObjectClass
{
    /** @var int The maximum number of top-level undo groups the receiver holds. An integer specifying the number of undo groups. A limit of 0 indicates no limit, so old undo groups are never dropped. When ending an undo group results in the number of groups exceeding this limit, the oldest groups are dropped from the stack. The default is 0. If you change the limit to a level below the prior limit, old undo groups are immediately dropped. */
    private(set) int $levelsOfUndo = 0 {
        set {
            $this->levelsOfUndo = $value;
            // A limit of 0 means no limit, so there is nothing to trim; without this guard setting it to 0 emptied both stacks instead of lifting the cap.
            if ($value === 0) {
                return;
            }
            while ($this->undoStack->count > $value) {
                $this->undoStack->removeAt(0);
            }
            while ($this->redoStack->count > $value) {
                $this->redoStack->removeAt(0);
            }
        }
    }
    /** @var bool A Boolean value that indicates whether the receiver automatically creates undo groups around each pass of the run loop. True if the receiver automatically creates undo groups around each pass of the run loop, otherwise false. The default is true. If you turn automatic grouping off, you must close groups explicitly before invoking either {@see undo()} or {@see undoNestedGroup()}. */
    public bool $groupsByEvent = true;
    /** @var int The number of nested undo groups (or redo groups, if Redo was invoked last) in the current event loop. An integer indicating the number of nested groups. If 0 is returned, there is no open undo or redo group. */
    private(set) int $groupingLevel = 0;
    /** @var bool A Boolean value that indicates whether the recording of undo operations is enabled. */
    private(set) bool $isUndoRegistrationEnabled = true;
    /** @var bool Returns a Boolean value that indicates whether the receiver is in the process of performing its {@see undo()} or {@see undoNestedGroup()} method. */
    private(set) bool $isUndoing = false;
    /** @var bool Returns a Boolean value that indicates whether the receiver is in the process of performing its {@see redo()} method. */
    private(set) bool $isRedoing = false;
    /** @var bool Boolean value that indicates whether the next undo action is discardable. */
    private(set) bool $undoActionIsDiscardable = false;
    /** @var bool Boolean value that indicates whether the next redo action is discardable. */
    private(set) bool $redoActionIsDiscardable = false;
    /** @var ArrayClass<UndoGroup> */
    private ArrayClass $undoStack;
    /** @var ArrayClass<UndoGroup> */
    private ArrayClass $redoStack;
    private ?object $nextTarget = null;
    private ?UndoGroup $group = null;
    /** @var bool A Boolean value that indicates whether the receiver has any actions to undo. */
    public bool $canUndo {
        get => !$this->undoStack->isEmpty || $this->group?->actions->isEmpty === false;
    }
    /** @var bool A Boolean value that indicates whether the receiver has any actions to redo. True if the receiver has any actions to redo, otherwise false. Because any undo operation registered clears the redo stack, this method posts an {@see UndoManagerCheckpointNotification} to allow clients to apply their pending operations before testing the redo stack. */
    public bool $canRedo {
        get {
            NotificationCenter::default()->postNotificationName(UndoManagerCheckpointNotification, $this);
            return !$this->redoStack->isEmpty;
        }
    }
    /** @var string The name identifying the redo action. */
    public string $redoActionName {
        get => $this->redoStack->last?->actionName ?? "";
    }
    /** @var string The name identifying the undo action. */
    public string $undoActionName {
        get => $this->group?->actionName ?? $this->undoStack->last?->actionName ?? "";
    }
    /** @var string $redoMenuItemTitle The complete title of the Redo menu command, for example, "Redo Paste." */
    public string $redoMenuItemTitle {
        get => $this->redoMenuTitle($this->redoActionName);
    }
    /** @var string The complete title of the Undo menu command, for example, "Undo Paste." */
    public string $undoMenuItemTitle {
        get => $this->undoMenuTitle($this->undoActionName);
    }

    public function __construct()
    {
        $this->undoStack = new ArrayClass();
        $this->redoStack = new ArrayClass();
    }

    private function begin(): void
    {
        $parent = $this->group;
        $this->group = new UndoGroup($parent);
        // groupingLevel counts the open groups, and nothing was maintaining it: it stayed at 0 for the lifetime of the manager, so undo() never closed the group opened for it and always failed with "Undo with nested groups" — the plain register-then-undo flow could not complete.
        $this->groupingLevel++;
        if (!$this->isUndoing && !$this->isRedoing) {
            NotificationCenter::default()->postNotificationName(UndoManagerDidOpenUndoGroupNotification, $this);
        }
    }

    /**
     * Registers the selector of the specified target to implement a single undo operation that the target receives.
     * @param object $target The target of the undo operation.
     * @param string $selector The selector for the undo operation.
     * @param mixed $object The argument sent with the selector.
     */
    public function registerUndo(object $target, string $selector, mixed $object): void
    {
        if (!$this->isUndoRegistrationEnabled) {
            return;
        }
        if (!$this->group instanceof UndoGroup) {
            if (!$this->groupsByEvent) {
                fatal_error("registerUndo() without beginUndoGrouping()");
            }
            $this->begin();
        }
        /** @var UndoGroup $group */
        $group = $this->group;
        $invocation = new Invocation();
        $invocation->target = $target;
        $invocation->selector = $selector;
        $invocation->arguments->append($object);
        $group->addInvocation($invocation);
        if (!$this->isUndoing && !$this->isRedoing) {
            $this->redoStack->removeAll();
        }
    }

    /**
     * Prepares the undo manager for invocation-based undo with the given target as the subject of the next undo operation.
     * @param object $target The target of the undo operation.
     * @return UndoManager A proxy object that forwards messages to the undo manager for recording as undo actions.
     */
    public function prepare(object $target): UndoManager
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
            fatal_error("Undo with nested groups");
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
            fatal_error("undoNestedGroup() while undoing or redoing");
        }
        if ($this->undoStack->isEmpty) {
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
        // Raised for the duration of the undo, the way redo() raises isRedoing: it is what tells endUndoGrouping() to file the actions the undone operation re-registers onto the redo stack instead of the undo stack. Nothing set it before, so a redo was never possible — the re-registered group landed back on the undo stack and the redo stack stayed empty.
        $this->isUndoing = true;
        $this->begin();
        $groupToUndo->perform();
        $this->endUndoGrouping();
        $this->isUndoing = false;
        $this->group = $oldGroup;
        if ($e = $this->redoStack->last) {
            $e->actionName = $groupToUndo->actionName;
        }
        NotificationCenter::default()->postNotificationName(UndoManagerDidUndoChangeNotification, $this);
    }

    /**
     * Performs the operations in the last group on the redo stack if there are any, recording them on the undo stack as a single group.
     *
     * Raises an InternalInconsistencyException if the method is invoked during an undo operation.
     * This method posts an {@see UndoManagerCheckpointNotification} and {@see UndoManagerWillRedoChangeNotification} before it performs the redo operation, and it posts the {@see UndoManagerDidRedoChangeNotification} after it performs the redo operation.
     */
    public function redo(): void
    {
        if ($this->isUndoing || $this->isRedoing) {
            fatal_error("Redo while undoing or redoing");
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
        if ($e = $this->undoStack->last) {
            $e->actionName = $group->actionName;
        }
        NotificationCenter::default()->postNotificationName(UndoManagerDidRedoChangeNotification, $this);
    }

    /**
     * Marks the beginning of an undo group.
     *
     * All individual undo operations before a subsequent {@see endUndoGrouping()} message are grouped together and reversed by a later {@see undo()} message. By default, undo groups are begun automatically at the start of the event loop, but you can begin your own undo groups with this method and nest them within other groups. This method posts an {@see UndoManagerCheckpointNotification} unless a top-level undo is in progress. It posts an {@see UndoManagerDidOpenUndoGroupNotification} if a new group was successfully created.
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
            fatal_error("endUndoGrouping() without beginUndoGrouping()");
        }
        NotificationCenter::default()->postNotificationName(UndoManagerCheckpointNotification, $this);
        if (!$this->isUndoing && !$this->isRedoing) {
            NotificationCenter::default()->postNotificationName(UndoManagerWillCloseUndoGroupNotification, $this);
        }
        NotificationCenter::default()->postNotificationName(UndoManagerDidCloseUndoGroupNotification, $this);
        $parent = $group->parent;
        $this->group = $parent;
        $this->groupingLevel--;
        $group->parent = null;
        if (!$parent instanceof UndoGroup) {
            // A limit of 0 is no limit: comparing it against the count made the condition true precisely when the stack was empty, and the drop-the-oldest call then indexed past the end.
            $limit = $this->levelsOfUndo;
            if ($this->isUndoing) {
                if ($limit > 0 && $this->redoStack->count >= $limit && !$group->actions->isEmpty) {
                    $this->redoStack->removeAt(0);
                }
                if (!$group->actions->isEmpty) {
                    $this->redoStack->append($group);
                }
            } else {
                if ($limit > 0 && $this->undoStack->count >= $limit && !$group->actions->isEmpty) {
                    $this->undoStack->removeAt(0);
                }
                if (!$group->actions->isEmpty) {
                    $this->undoStack->append($group);
                }
            }
        } else {
            $group->actions->forEach(fn(Invocation $action) => $parent->addInvocation($action));
        }
    }

    #[Override]
    public function forwardInvocation(Invocation $invocation): void
    {
        if (!$this->isUndoRegistrationEnabled) {
            return;
        }
        $nextTarget = $this->nextTarget ?? fatal_error("forwardInvocation() without preparation");
        if ($this->group === null) {
            $this->groupsByEvent ?: fatal_error("forwardInvocation() without beginUndoGrouping()");
            $this->begin();
        }
        /** @var UndoGroup $group */
        $group = $this->group;
        $invocation->target = $nextTarget;
        $group->addInvocation($invocation);
        if (!$this->isUndoing && !$this->isRedoing && !$group->actions->isEmpty) {
            $this->redoStack->removeAll();
        }
        $this->nextTarget = null;
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
     * Because undo registration is enabled by default, it is often used to balance a prior {@see disableUndoRegistration()} message. Undo registration isn't re-enabled until an enabled message balances the last disabled message in effect. Raises an InternalInconsistencyException if invoked while no {@see disableUndoRegistration()} message is in effect.
     */
    public function enableUndoRegistration(): void
    {
        !$this->isUndoRegistrationEnabled ?: fatal_error();
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
        $i = $redoStack->endIndex;
        while ($i-- > 0) {
            $g = $redoStack[$i];
            if (!$g->removeActions($target)) {
                $redoStack->removeAt($i);
            }
        }
        $i = $undoStack->endIndex;
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
     * If actionName is an empty string, the action name currently associated with the menu command is removed. There is no effect if actionName is null.
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
        $name = localized_string("Undo");
        if ($actionName === "") {
            return $name;
        }
        return "$name $actionName";
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
        $name = localized_string("Redo");
        if ($actionName === "") {
            return $name;
        }
        return "$name $actionName";
    }

    /**
     * Sets whether the next undo or redo action is discardable.
     * @param bool $discardable Specifies if the action is discardable. True if the next undo or redo action can be discarded; false otherwise.
     */
    public function setActionIsDiscardable(bool $discardable): void
    {
    }
}
