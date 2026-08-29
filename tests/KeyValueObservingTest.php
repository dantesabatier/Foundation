<?php

declare(strict_types=1);

namespace Sabatier\Foundation\Tests;

use PHPUnit\Framework\TestCase;
use Sabatier\Foundation\KeyValueChange;
use Sabatier\Foundation\KeyValueObservedChange;
use Sabatier\Foundation\KeyValueObservingOptions;
use Sabatier\Foundation\ObjectClass;

/**
 * An observed object: KVO notifies through valueForKey, so the observed state has to be
 * a real property rather than something derived.
 */
final class ObservedAccount extends ObjectClass
{
    public int $balance = 100;
    public string $holder = "sin asignar";
    /** @var int $uninitialised A declared typed property left unset, which hasProperty() answers true for and reading throws on. */
    public int $uninitialised;
}

/**
 * A classic observer, recording what each notification carried.
 */
final class RecordingObserver extends ObjectClass
{
    /** @var list<array{keyPath: string, new: mixed, old: mixed, prior: bool, kind: KeyValueChange, context: mixed}> $notifications */
    public array $notifications = [];

    public function observeValue(string $keyPath, mixed $object, KeyValueObservedChange $change, mixed $context = null): void
    {
        $this->notifications[] = [
            "keyPath" => $keyPath,
            "new" => $change->newValue,
            "old" => $change->oldValue,
            "prior" => $change->isPrior,
            "kind" => $change->kind,
            "context" => $context,
        ];
    }
}

/**
 * Tests for the key-value observing half of src/ObjectClass.php.
 *
 * Regression guards:
 *  - didChangeValueForKey() reported the new value as oldValue. setValueForKey() passes the
 *    value being written to both notifications, and didChangeValueForKey() assigned that to
 *    $change->oldValue, so an observer that read oldValue from the did-notification — the
 *    natural one, since it is the notification that confirms the change — got the value that
 *    had just replaced the old one. willChangeValueForKey() now records what the key holds
 *    before it is overwritten, guarded by isset() rather than hasProperty(): a declared but
 *    uninitialised typed property answers true to the latter and throws when read, which is
 *    the state every CoreData description object is in while being populated;
 *  - KeyValueObservingOptions::initial did nothing. Registering an observer with it sent no
 *    notification at all, so an observer could not prime itself through the same path that
 *    handles later changes;
 *  - setValueForKey() announced KeyValueChange::replacement for a plain assignment. Assigning
 *    is a setting; replacement describes an indexed element swapped inside a collection, which
 *    is what CoreData's FaultingSet reports for its own mutations;
 *  - oldValue was suppressed in willChangeValueForKey() unless the kind was something other
 *    than setting, so the manual willChange/didChange pair that the interface documents for
 *    non-automatic properties never carried the previous value.
 */
final class KeyValueObservingTest extends TestCase
{
    public function testDidChangeReportsThePreviousValueAsOld(): void
    {
        $account = new ObservedAccount();
        $observer = new RecordingObserver();
        $account->addObserver($observer, "balance", KeyValueObservingOptions::new | KeyValueObservingOptions::old);

        $account->setValueForKey(250, "balance");

        $this->assertCount(2, $observer->notifications);
        foreach ($observer->notifications as $index => $notification) {
            $this->assertSame(250, $notification["new"], "new value of notification $index");
            $this->assertSame(100, $notification["old"], "old value of notification $index");
        }
    }

    public function testAssignmentIsASettingNotAReplacement(): void
    {
        $account = new ObservedAccount();
        $observer = new RecordingObserver();
        $account->addObserver($observer, "balance", KeyValueObservingOptions::new);

        $account->setValueForKey(7, "balance");

        foreach ($observer->notifications as $notification) {
            $this->assertSame(KeyValueChange::setting, $notification["kind"]);
        }
    }

    public function testInitialOptionNotifiesOnRegistration(): void
    {
        $account = new ObservedAccount();
        $observer = new RecordingObserver();

        $account->addObserver($observer, "balance", KeyValueObservingOptions::initial | KeyValueObservingOptions::new);

        $this->assertCount(1, $observer->notifications);
        $this->assertSame(100, $observer->notifications[0]["new"]);
        $this->assertFalse($observer->notifications[0]["prior"]);
    }

    public function testWithoutTheInitialOptionRegistrationIsSilent(): void
    {
        $account = new ObservedAccount();
        $observer = new RecordingObserver();

        $account->addObserver($observer, "balance", KeyValueObservingOptions::new);

        $this->assertSame([], $observer->notifications);
    }

    public function testManualWillAndDidChangeCarryThePreviousValue(): void
    {
        // The flow the interface documents for a property that opts out of automatic notification: the setter announces the change itself, with the default kind.
        $account = new ObservedAccount();
        $observer = new RecordingObserver();
        $account->addObserver($observer, "balance", KeyValueObservingOptions::new | KeyValueObservingOptions::old);

        $account->willChangeValueForKey("balance");
        $account->balance = 777;
        $account->didChangeValueForKey("balance");

        $this->assertCount(2, $observer->notifications);
        $this->assertSame(100, $observer->notifications[0]["old"], "will-notification");
        $this->assertSame(100, $observer->notifications[1]["old"], "did-notification");
        $this->assertSame(777, $observer->notifications[1]["new"]);
    }

    public function testAnnouncingAChangeOnAnUninitialisedPropertyDoesNotThrow(): void
    {
        // hasProperty() answers true for a declared typed property that was never assigned, and reading it raises "must not be accessed before initialization". Every CoreData description object passes through that state while being populated.
        $account = new ObservedAccount();
        $observer = new RecordingObserver();
        $account->addObserver($observer, "uninitialised", KeyValueObservingOptions::new | KeyValueObservingOptions::old);

        $account->setValueForKey(5, "uninitialised");

        $this->assertSame(5, $observer->notifications[1]["new"]);
        $this->assertNull($observer->notifications[1]["old"]);
    }

    public function testObserveWithAClosureReceivesTheChange(): void
    {
        $account = new ObservedAccount();
        /** @var list<array{new: mixed, old: mixed}> $changes */
        $changes = [];
        $account->observe("balance", KeyValueObservingOptions::new | KeyValueObservingOptions::old, function (mixed $object, KeyValueObservedChange $change) use (&$changes): void {
            $changes[] = ["new" => $change->newValue, "old" => $change->oldValue];
        });

        $account->setValueForKey(42, "balance");

        $this->assertCount(2, $changes);
        $this->assertSame(42, $changes[1]["new"]);
        $this->assertSame(100, $changes[1]["old"]);
    }

    public function testObserveHonoursTheInitialOption(): void
    {
        $account = new ObservedAccount();
        /** @var list<mixed> $values */
        $values = [];
        $account->observe("balance", KeyValueObservingOptions::initial | KeyValueObservingOptions::new, function (mixed $object, KeyValueObservedChange $change) use (&$values): void {
            $values[] = $change->newValue;
        });

        $this->assertSame([100], $values);
    }

    public function testRemoveObserverStopsNotifications(): void
    {
        $account = new ObservedAccount();
        $observer = new RecordingObserver();
        $account->addObserver($observer, "balance", KeyValueObservingOptions::new);
        $account->setValueForKey(1, "balance");
        $delivered = count($observer->notifications);

        $account->removeObserver($observer, "balance");
        $account->setValueForKey(2, "balance");

        $this->assertCount($delivered, $observer->notifications);
    }

    public function testPriorOptionMarksTheFirstNotification(): void
    {
        $account = new ObservedAccount();
        $observer = new RecordingObserver();
        $account->addObserver($observer, "balance", KeyValueObservingOptions::prior | KeyValueObservingOptions::new | KeyValueObservingOptions::old);

        $account->setValueForKey(9, "balance");

        $this->assertCount(2, $observer->notifications);
        $this->assertTrue($observer->notifications[0]["prior"], "the will-notification is the prior one");
        $this->assertFalse($observer->notifications[1]["prior"]);
    }

    public function testOptionsFilterWhatTheChangeCarries(): void
    {
        $account = new ObservedAccount();
        $observer = new RecordingObserver();
        $account->addObserver($observer, "balance", KeyValueObservingOptions::new);

        $account->setValueForKey(3, "balance");

        foreach ($observer->notifications as $notification) {
            $this->assertNull($notification["old"], "old must be withheld when it was not requested");
        }
        $this->assertSame(3, $observer->notifications[1]["new"]);
    }

    public function testContextIsPassedThrough(): void
    {
        $account = new ObservedAccount();
        $observer = new RecordingObserver();
        $account->addObserver($observer, "balance", KeyValueObservingOptions::new, "el-contexto");

        $account->setValueForKey(4, "balance");

        $this->assertSame("el-contexto", $observer->notifications[0]["context"]);
    }

    public function testOnlyTheObservedKeyNotifies(): void
    {
        $account = new ObservedAccount();
        $observer = new RecordingObserver();
        $account->addObserver($observer, "balance", KeyValueObservingOptions::new);

        $account->setValueForKey("otro", "holder");

        $this->assertSame([], $observer->notifications);
    }

    public function testSeveralObserversEachReceiveTheirOwnChange(): void
    {
        $account = new ObservedAccount();
        $wantsNew = new RecordingObserver();
        $wantsOld = new RecordingObserver();
        $account->addObserver($wantsNew, "balance", KeyValueObservingOptions::new);
        $account->addObserver($wantsOld, "balance", KeyValueObservingOptions::old);

        $account->setValueForKey(11, "balance");

        $this->assertSame(11, $wantsNew->notifications[1]["new"]);
        $this->assertNull($wantsNew->notifications[1]["old"]);
        $this->assertSame(100, $wantsOld->notifications[1]["old"]);
        $this->assertNull($wantsOld->notifications[1]["new"]);
    }

    public function testConsecutiveChangesEachReportTheirOwnPreviousValue(): void
    {
        // The recorded previous value must not leak from one change into the next.
        $account = new ObservedAccount();
        $observer = new RecordingObserver();
        $account->addObserver($observer, "balance", KeyValueObservingOptions::new | KeyValueObservingOptions::old);

        $account->setValueForKey(200, "balance");
        $account->setValueForKey(300, "balance");

        $this->assertSame(100, $observer->notifications[1]["old"], "first change");
        $this->assertSame(200, $observer->notifications[3]["old"], "second change");
        $this->assertSame(300, $observer->notifications[3]["new"]);
    }
}
