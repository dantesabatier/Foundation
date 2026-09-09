<?php

declare(strict_types=1);

namespace Sabatier\Foundation\Tests;

use Sabatier\Foundation\Invocation;
use Sabatier\Foundation\InternalInconsistencyException;
use Sabatier\Foundation\ObjectClass;
use Sabatier\Foundation\UndoManager;
use PHPUnit\Framework\TestCase;

/**
 * A target for invocation-based undo: it holds a value and exposes the setter the
 * recorded invocation calls back, without registering anything itself.
 */
final class UndoManagerInvocationDocument extends ObjectClass
{
    public string $text = "v0";

    /**
     * Applies a value, the way a recorded invocation does when it is undone.
     * @param mixed $value The value to store, coerced to a string.
     */
    public function setText(mixed $value): void
    {
        $this->text = (string)$value;
    }
}

/**
 * Tests the parts of src/UndoManager.php that UndoManagerTest leaves alone: the
 * invocation-based registration path (prepare/forwardInvocation), targeted action
 * removal, and the grouping contract when groupsByEvent is off.
 *
 * Regression guards:
 *  - prepare() names the target of the next recorded invocation and answers the manager
 *    itself, so forwardInvocation() has somewhere to send the call;
 *  - forwardInvocation() refuses to record without that preparation, and clears it
 *    afterwards so a second invocation cannot silently reuse the previous target;
 *  - a disabled manager records neither through registerUndo() nor through an
 *    invocation;
 *  - removeAllActions() with a target drops only that target's actions and leaves the
 *    rest of the stack undoable; with null it clears everything;
 *  - with groupsByEvent off, registering outside an explicit group is a fatal error
 *    rather than an implicitly opened group.
 *
 * Note: PHP has no message forwarding, so nothing calls forwardInvocation() on the
 * manager's behalf the way the Objective-C runtime would. The caller builds the
 * Invocation and hands it over, which is what these tests do.
 */
final class UndoManagerInvocationTest extends TestCase
{
    private function invocation(string $selector, mixed $argument): Invocation
    {
        $invocation = new Invocation();
        $invocation->selector = $selector;
        $invocation->arguments->append($argument);
        return $invocation;
    }

    public function testPrepareAnswersTheManagerItself(): void
    {
        $manager = new UndoManager();

        $this->assertSame($manager, $manager->prepare(new UndoManagerInvocationDocument()), "the manager is its own proxy");
    }

    public function testAPreparedInvocationIsRecordedAndReplayedOnUndo(): void
    {
        $manager = new UndoManager();
        $document = new UndoManagerInvocationDocument();
        $document->setText("v1");

        $manager->prepare($document)->forwardInvocation($this->invocation("setText", "v0"));

        $this->assertTrue($manager->canUndo);

        $manager->undo();

        $this->assertSame("v0", $document->text, "undoing sends the recorded invocation to the prepared target");
    }

    public function testForwardingWithoutPreparationIsFatal(): void
    {
        $manager = new UndoManager();

        $this->expectException(InternalInconsistencyException::class);
        $this->expectExceptionMessage("without preparation");

        $manager->forwardInvocation($this->invocation("setText", "v0"));
    }

    public function testPreparationIsConsumedByTheInvocationItNames(): void
    {
        $manager = new UndoManager();
        $document = new UndoManagerInvocationDocument();

        $manager->prepare($document)->forwardInvocation($this->invocation("setText", "v0"));

        // The target is cleared once used, so a second invocation needs its own prepare().
        $this->expectException(InternalInconsistencyException::class);
        $this->expectExceptionMessage("without preparation");

        $manager->forwardInvocation($this->invocation("setText", "v1"));
    }

    public function testADisabledManagerIgnoresAnInvocation(): void
    {
        $manager = new UndoManager();
        $document = new UndoManagerInvocationDocument();

        $manager->disableUndoRegistration();
        $manager->prepare($document)->forwardInvocation($this->invocation("setText", "v0"));

        $this->assertFalse($manager->canUndo, "nothing is recorded while registration is disabled");

        $manager->enableUndoRegistration();
        $manager->prepare($document)->forwardInvocation($this->invocation("setText", "v0"));

        $this->assertTrue($manager->canUndo, "re-enabling restores recording");
    }

    public function testRemovingOneTargetsActionsLeavesTheOthers(): void
    {
        $manager = new UndoManager();
        $removed = $this->registering($manager, "a1");
        $kept = $this->registering($manager, "b1");

        $manager->removeAllActions($removed);

        $this->assertTrue($manager->canUndo, "the other target's group is still undoable");

        $manager->undo();

        $this->assertSame("v0", $kept->text, "the surviving action is the one that runs");
        $this->assertFalse($manager->canUndo, "and it was the only one left");
    }

    public function testRemovingEveryActionEmptiesTheStack(): void
    {
        $manager = new UndoManager();
        $this->registering($manager, "a1");

        $manager->removeAllActions(null);

        $this->assertFalse($manager->canUndo);
        $this->assertFalse($manager->canRedo);
    }

    public function testWithoutAutomaticGroupingRegistrationNeedsAnExplicitGroup(): void
    {
        $manager = new UndoManager();
        $manager->groupsByEvent = false;
        $document = new UndoManagerInvocationDocument();

        $this->expectException(InternalInconsistencyException::class);
        $this->expectExceptionMessage("without beginUndoGrouping()");

        $manager->registerUndo($document, "setText", "v0");
    }

    public function testWithoutAutomaticGroupingAnExplicitGroupStillRecords(): void
    {
        $manager = new UndoManager();
        $manager->groupsByEvent = false;
        $document = new UndoManagerInvocationDocument();
        $document->setText("v1");

        $manager->beginUndoGrouping();
        $manager->registerUndo($document, "setText", "v0");
        $manager->endUndoGrouping();

        $this->assertTrue($manager->canUndo);

        $manager->undo();

        $this->assertSame("v0", $document->text);
    }

    public function testSetActionIsDiscardableIsNotImplemented(): void
    {
        $manager = new UndoManager();
        $document = new UndoManagerInvocationDocument();
        $manager->registerUndo($document, "setText", "v0");

        $manager->setActionIsDiscardable(true);

        // The method is a stub: it has an empty body, and neither flag is ever assigned
        // anywhere in the class. This pins the current behaviour so implementing it
        // later is a deliberate change rather than a silent one.
        $this->assertFalse($manager->undoActionIsDiscardable);
        $this->assertFalse($manager->redoActionIsDiscardable);
    }

    /** Registers one undo action for a fresh document inside its own group. */
    private function registering(UndoManager $manager, string $value): UndoManagerInvocationDocument
    {
        $document = new UndoManagerInvocationDocument();
        $document->setText($value);
        $manager->beginUndoGrouping();
        $manager->registerUndo($document, "setText", "v0");
        $manager->endUndoGrouping();
        return $document;
    }
}
