<?php

declare(strict_types=1);

namespace Sabatier\Foundation\Tests;

use PHPUnit\Framework\TestCase;
use Sabatier\Foundation\ObjectClass;
use Sabatier\Foundation\UndoManager;

/**
 * A document following the Cocoa undo pattern: the setter registers how to get back to the
 * value it is about to overwrite, on every call — including the ones the undo manager itself
 * makes while undoing, which is what populates the redo stack.
 */
final class UndoableDocument extends ObjectClass
{
    public string $text = "v0";
    public ?UndoManager $undoManager = null;
    /** @var list<string> $applied Every value the setter was asked to apply, in order. */
    public array $applied = [];

    public function setText(mixed $value): void
    {
        $this->undoManager?->registerUndo($this, "setText", $this->text);
        $this->applied[] = (string)$value;
        $this->text = (string)$value;
    }
}

/**
 * Tests for src/UndoManager.php.
 *
 * Regression guards:
 *  - groupingLevel was declared, read once by undo(), and never assigned. It stayed at 0
 *    for the lifetime of the manager, so undo() never closed the group registerUndo() had
 *    opened and fell straight into "Undo with nested groups" — the plain register-then-undo
 *    flow could not complete at all. begin() and endUndoGrouping() maintain it now;
 *  - isUndoing was only ever set to false. Nothing raised it, so endUndoGrouping() could not
 *    tell an undo in progress from ordinary registration and filed the actions the undone
 *    operation re-registered back onto the undo stack. The redo stack therefore stayed empty
 *    and redo() was a no-op. It is raised for the duration of the undo now, mirroring how
 *    redo() already raised isRedoing;
 *  - levelsOfUndo defaults to 0, which its own documentation defines as "no limit", but the
 *    limit check compared it against the stack count with `===`. That was true precisely
 *    when the stack was empty, and the drop-the-oldest call then indexed past the end and
 *    raised. The setter had the same defect, so lifting the cap emptied both stacks;
 *  - undoMenuTitle() returned the localized "Redo" and redoMenuTitle() returned "Undo" —
 *    the two were crossed.
 */
final class UndoManagerTest extends TestCase
{
    private function document(UndoManager $manager): UndoableDocument
    {
        $document = new UndoableDocument();
        $document->undoManager = $manager;
        return $document;
    }

    public function testRegisterThenUndoRestoresThePreviousValue(): void
    {
        $manager = new UndoManager();
        $document = $this->document($manager);

        $document->setText("v1");
        $this->assertTrue($manager->canUndo);

        $manager->undo();

        $this->assertSame("v0", $document->text);
    }

    public function testUndoThenRedoReturnsToTheChangedValue(): void
    {
        $manager = new UndoManager();
        $document = $this->document($manager);
        $document->setText("v1");

        $manager->undo();
        $this->assertSame("v0", $document->text);
        $this->assertTrue($manager->canRedo, "undoing must leave something to redo");

        $manager->redo();

        $this->assertSame("v1", $document->text);
        $this->assertTrue($manager->canUndo, "redoing must leave something to undo");
    }

    public function testUndoAndRedoCanAlternateRepeatedly(): void
    {
        $manager = new UndoManager();
        $document = $this->document($manager);
        $document->setText("v1");

        $manager->undo();
        $manager->redo();
        $manager->undo();

        $this->assertSame("v0", $document->text);
    }

    public function testGroupingLevelTracksOpenGroups(): void
    {
        $manager = new UndoManager();

        $this->assertSame(0, $manager->groupingLevel);

        // With groupsByEvent on, beginUndoGrouping() opens the event group as well as the
        // explicit one, so it takes two closes to get back to zero.
        $manager->beginUndoGrouping();
        $this->assertSame(2, $manager->groupingLevel);

        $manager->endUndoGrouping();
        $this->assertSame(1, $manager->groupingLevel);

        $manager->endUndoGrouping();
        $this->assertSame(0, $manager->groupingLevel);
    }

    public function testGroupingLevelReturnsToZeroAfterAnUndo(): void
    {
        $manager = new UndoManager();
        $document = $this->document($manager);

        $document->setText("v1");
        $this->assertSame(1, $manager->groupingLevel, "registerUndo opens the event group");

        $manager->undo();

        $this->assertSame(0, $manager->groupingLevel);
    }

    public function testAGroupUndoesAsASingleStep(): void
    {
        $manager = new UndoManager();
        $document = $this->document($manager);
        $manager->beginUndoGrouping();
        $manager->registerUndo($document, "setText", "a");
        $manager->registerUndo($document, "setText", "b");
        $manager->endUndoGrouping();
        $document->applied = [];

        $manager->undo();

        $this->assertCount(2, $document->applied, "both actions belong to one undo step");
    }

    public function testDisablingRegistrationDropsTheAction(): void
    {
        $manager = new UndoManager();
        $document = $this->document($manager);

        $manager->disableUndoRegistration();
        $manager->registerUndo($document, "setText", "x");
        $this->assertFalse($manager->canUndo);

        $manager->enableUndoRegistration();
        $manager->registerUndo($document, "setText", "y");
        $this->assertTrue($manager->canUndo);
    }

    public function testRemoveAllActionsEmptiesTheStack(): void
    {
        $manager = new UndoManager();
        $document = $this->document($manager);
        $manager->registerUndo($document, "setText", "z");

        $manager->removeAllActions(null);

        $this->assertFalse($manager->canUndo);
    }

    public function testMenuTitlesNameTheirOwnCommand(): void
    {
        $manager = new UndoManager();

        $this->assertStringContainsString("Undo", $manager->undoMenuTitle("Escribir"));
        $this->assertStringNotContainsString("Redo", $manager->undoMenuTitle("Escribir"));
        $this->assertStringContainsString("Redo", $manager->redoMenuTitle("Escribir"));
        $this->assertStringNotContainsString("Undo", $manager->redoMenuTitle("Escribir"));
    }

    public function testMenuTitlesAppendTheActionName(): void
    {
        $manager = new UndoManager();

        $this->assertStringEndsWith("Escribir", $manager->undoMenuTitle("Escribir"));
        $this->assertStringEndsWith("Escribir", $manager->redoMenuTitle("Escribir"));
    }

    public function testMenuTitlesWithoutAnActionNameAreJustTheCommand(): void
    {
        $manager = new UndoManager();

        $this->assertSame($manager->undoMenuTitle(""), trim($manager->undoMenuTitle("")));
        $this->assertStringContainsString("Undo", $manager->undoMenuTitle(""));
    }

    public function testManyRegistrationsNeitherDropNorRaise(): void
    {
        // The default levelsOfUndo of 0 means "no limit", and the limit check used to fire
        // exactly when the stack was empty and then index past its end. Registering a run of
        // actions must simply work.
        //
        // Without a run loop the event group opened by the first registerUndo() is never
        // closed on its own, so consecutive changes accumulate into that one group and undo
        // reverts them together; testClosedGroupsNestIntoTheOpenEventGroup records the same
        // effect for explicitly bracketed groups.
        $manager = new UndoManager();
        $document = $this->document($manager);

        for ($index = 1; $index <= 5; $index++) {
            $document->setText("v$index");
        }
        $this->assertSame("v5", $document->text);
        $this->assertTrue($manager->canUndo);

        $manager->undo();

        $this->assertSame("v4", $document->text, "the group's actions replay in order, ending on the most recent");
    }

    public function testClosedGroupsNestIntoTheOpenEventGroup(): void
    {
        // groupsByEvent leaves an event group open for the whole run, and a group closed
        // inside it folds its actions into that parent rather than becoming a top-level
        // step. Cocoa closes the event group at the end of each run loop pass; there is no
        // run loop here, so the one undo reverts the whole run. Pinned as it behaves — the
        // step granularity a caller gets without a run loop is a design question, not
        // something these tests should decide.
        $manager = new UndoManager();
        $document = $this->document($manager);

        foreach (["v1", "v2", "v3"] as $value) {
            $manager->beginUndoGrouping();
            $document->setText($value);
            $manager->endUndoGrouping();
            $this->assertSame(1, $manager->groupingLevel, "the event group stays open");
        }

        $manager->undo();

        $this->assertSame("v2", $document->text);
        $this->assertFalse($manager->canUndo, "the run collapsed into a single step");
    }

    public function testAnUndoRegistersItsOwnInverse(): void
    {
        // The mechanism the redo stack depends on: the value the undo applies is itself
        // recorded, so the change can be replayed.
        $manager = new UndoManager();
        $document = $this->document($manager);
        $document->setText("v1");
        $document->applied = [];

        $manager->undo();

        $this->assertSame(["v0"], $document->applied);
        $this->assertTrue($manager->canRedo);
    }

    public function testActionNameIsReported(): void
    {
        $manager = new UndoManager();
        $document = $this->document($manager);
        $manager->registerUndo($document, "setText", "w");

        $manager->setActionName("Escribir");

        $this->assertSame("Escribir", $manager->undoActionName);
    }
}
