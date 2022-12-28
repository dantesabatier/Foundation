<?php

namespace Sabatier\Foundation;

/**
 * A general-purpose recorder of operations that enables undo and redo.
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
    protected bool $canUndo = false;
    protected bool $canRedo = false;
    /** @var int The maximum number of top-level undo groups the receiver holds. An integer specifying the number of undo groups. A limit of 0 indicates no limit, so old undo groups are never dropped. When ending an undo group results in the number of groups exceeding this limit, the oldest groups are dropped from the stack. The default is 0. If you change the limit to a level below the prior limit, old undo groups are immediately dropped. */
    public int $levelsOfUndo = 0;
    /** @var bool A Boolean value that indicates whether the receiver automatically creates undo groups around each pass of the run loop. true if the receiver automatically creates undo groups around each pass of the run loop, otherwise false. The default is true. If you turn automatic grouping off, you must close groups explicitly before invoking either {@see undo()} or {@see undoNestedGroup()}. */
    public bool $groupsByEvent = true;
    protected int $groupingLevel = 0;
    protected bool $isUndoRegistrationEnabled = false;
    protected bool $isUndoing = false;
    protected bool $isRedoing = false;
    protected string $undoActionName = "";
    protected string $redoActionName = "";
    protected string $undoMenuItemTitle = "Undo";
    protected string $redoMenuItemTitle = "Redo";
    protected bool $undoActionIsDiscardable = false;
    protected bool $redoActionIsDiscardable = false;

    public function __get(string $name)
    {
        return match ($name) {
            "canUndo", "canRedo", "groupingLevel", "isUndoRegistrationEnabled", "isUndoing", "isRedoing", "undoActionName", "redoActionName", "undoMenuItemTitle", "redoMenuItemTitle", "undoActionIsDiscardable", "redoActionIsDiscardable" => $this->$name,
            default => $this->valueForUndefinedKey($name)
        };
    }

    /**
     * Registers the selector of the specified target to implement a single undo operation that the target receives.
     * @param mixed $target The target of the undo operation.
     * @param string $selector The selector for the undo operation.
     * @param mixed $object The argument sent with the selector.
     */
    public function registerUndo(mixed $target, string $selector, mixed $object): void
    {
    }

    /**
     * Prepares the undo manager for invocation-based undo with the given target as the subject of the next undo operation.
     * @param mixed $target The target of the undo operation.
     * @return mixed A proxy object that forwards messages to the undo manager for recording as undo actions.
     */
    public function prepare(mixed $target): mixed
    {
        return null;
    }

    /**
     * Closes the top-level undo group if necessary and invokes {@see undoNestedGroup()}.
     *
     * This method also invokes {@see endUndoGrouping()} if the nesting level is 1. Raises an InternalInconsistencyException if more than one undo group is open (that is, if the last group isn’t at the top level).
     * This method posts an UndoManagerCheckpointNotification.
     */
    public function undo(): void
    {
    }

    /**
     * Performs the undo operations in the last undo group (whether top-level or nested), recording the operations on the redo stack as a single group.
     *
     * Raises an InternalInconsistencyException if any undo operations have been registered since the last {@see enableUndoRegistration()} message.
     * This method posts an NSUndoManagerCheckpointNotification and {@see UndoManagerWillUndoChangeNotification} before it performs the undo operation, and it posts an {@see UndoManagerDidUndoChangeNotification} after it performs the undo operation.
     */
    public function undoNestedGroup(): void
    {
    }

    /**
     * Performs the operations in the last group on the redo stack, if there are any, recording them on the undo stack as a single group.
     *
     * Raises an InternalInconsistencyException if the method is invoked during an undo operation.
     * This method posts an {@see UndoManagerCheckpointNotification} and {@see UndoManagerWillRedoChangeNotification} before it performs the redo operation, and it posts the {@see UndoManagerDidRedoChangeNotification} after it performs the redo operation.
     */
    public function redo(): void
    {
    }

    /**
     * Marks the beginning of an undo group.
     *
     * All individual undo operations before a subsequent {@see endUndoGrouping()} message are grouped together and reversed by a later {@see undo()} message. By default undo groups are begun automatically at the start of the event loop, but you can begin your own undo groups with this method, and nest them within other groups. This method posts an {@see UndoManagerCheckpointNotification} unless a top-level undo is in progress. It posts an {@see UndoManagerDidOpenUndoGroupNotification} if a new group was successfully created.
     */
    public function beginUndoGrouping(): void
    {
    }

    /**
     * Marks the end of an undo group.
     *
     * All individual undo operations back to the matching {@see beginUndoGrouping()} message are grouped together and reversed by a later undo or {@see undoNestedGroup()} message. Undo groups can be nested, thus providing functionality similar to nested transactions. Raises an InternalInconsistencyException if there’s no beginUndoGrouping message in effect. This method posts an {@see UndoManagerCheckpointNotification} and an {@see UndoManagerDidCloseUndoGroupNotification} just before the group is closed.
     */
    public function endUndoGrouping(): void
    {
    }

    /**
     * Disables the recording of undo operations, whether by {@see registerUndo()} or by invocation-based undo.
     *
     * This method can be invoked multiple times by multiple clients. The {@see enableUndoRegistration()} method must be invoked an equal number of times to re-enable undo registration.
     */
    public function disableUndoRegistration(): void
    {
    }

    /**
     * Enables the recording of undo operations.
     *
     * Because undo registration is enabled by default, it is often used to balance a prior {@see disableUndoRegistration()} message. Undo registration isn’t actually re-enabled until an enable message balances the last disable message in effect. Raises an InternalInconsistencyException if invoked while no {@see disableUndoRegistration()} message is in effect.
     */
    public function enableUndoRegistration(): void
    {
    }

    /**
     * Clears the undo and redo stacks of all operations involving the specified target as the recipient of the undo message.
     *
     * Doesn’t re-enable the receiver if it’s disabled.
     * @param mixed $target The recipient of the undo messages to be removed.
     */
    public function removeAllActions(mixed $target): void
    {
    }

    /**
     * Sets the name of the action associated with the Undo or Redo command.
     *
     * If actionName is an empty string, the action name currently associated with the menu command is removed. There is no effect if actionName is nil.
     * @param string $actionName The name of the action.
     */
    public function setActionName(string $actionName): void
    {
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
        return $actionName;
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
        return $actionName;
    }

    /**
     * Sets whether the next undo or redo action is discardable.
     * @param bool $discardable Specifies if the action is discardable. true if the next undo or redo action can be discarded; false otherwise.
     */
    public function setActionIsDiscardable(bool $discardable): void
    {
    }
}
