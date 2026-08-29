<?php

declare(strict_types=1);

namespace Sabatier\Foundation\Tests;

use PHPUnit\Framework\TestCase;
use Sabatier\Foundation\Dictionary;
use Sabatier\Foundation\Notification;
use Sabatier\Foundation\NotificationCenter;
use Sabatier\Foundation\ObjectClass;

/**
 * An observer registered by selector rather than by block.
 */
final class SelectorListener extends ObjectClass
{
    /** @var list<string> $received The name of every notification delivered. */
    public array $received = [];

    public function handleNotification(Notification $notification): void
    {
        $this->received[] = $notification->name;
    }
}

/**
 * Tests for src/NotificationCenter.php.
 *
 * Regression guard:
 *  - removeObserver() matched a null $name and $object literally instead of treating them
 *    as "any", which is what its own documentation says ("When null, the receiver does not
 *    use notification names as criteria for removal"). Since no entry is registered with a
 *    null name, removeObserver($observer) matched nothing and removed nothing — an observer
 *    could not be unregistered wholesale, and kept receiving notifications after what
 *    looked like a successful removal. It also removed only the first match, so an observer
 *    registered for several names under one call site lost one entry at a time. Matching is
 *    now "any" for a null criterion and drops every entry that matches, and it accepts
 *    either the object passed to addObserver() or the opaque entry addObserverForName()
 *    returns.
 */
final class NotificationCenterTest extends TestCase
{
    public function testBlockObserverReceivesItsNotification(): void
    {
        $center = new NotificationCenter();
        /** @var list<string> $received */
        $received = [];
        $center->addObserverForName("evento", null, function (Notification $notification) use (&$received): void {
            $received[] = $notification->name;
        });

        $center->postNotificationName("evento");

        $this->assertSame(["evento"], $received);
    }

    public function testADifferentNameDoesNotFire(): void
    {
        $center = new NotificationCenter();
        $fired = 0;
        $center->addObserverForName("evento", null, function () use (&$fired): void {
            $fired++;
        });

        $center->postNotificationName("otro");

        $this->assertSame(0, $fired);
    }

    public function testRemovingABlockObserverStopsItWithoutAffectingTheOthers(): void
    {
        $center = new NotificationCenter();
        $removed = 0;
        $kept = 0;
        $token = $center->addObserverForName("evento", null, function () use (&$removed): void {
            $removed++;
        });
        $center->addObserverForName("evento", null, function () use (&$kept): void {
            $kept++;
        });

        $center->removeObserver($token);
        $center->postNotificationName("evento");

        $this->assertSame(0, $removed, "the removed observer must not fire");
        $this->assertSame(1, $kept, "the other observer must still fire");
    }

    public function testSelectorObserverReceivesItsNotification(): void
    {
        $center = new NotificationCenter();
        $listener = new SelectorListener();
        $center->addObserver($listener, "handleNotification", "ping");

        $center->postNotificationName("ping");

        $this->assertSame(["ping"], $listener->received);
    }

    public function testRemoveObserverWithoutANameRemovesEveryRegistration(): void
    {
        // The regression: omitting the name means "any name", so both entries go.
        $center = new NotificationCenter();
        $listener = new SelectorListener();
        $center->addObserver($listener, "handleNotification", "primero");
        $center->addObserver($listener, "handleNotification", "segundo");

        $center->removeObserver($listener);
        $center->postNotificationName("primero");
        $center->postNotificationName("segundo");

        $this->assertSame([], $listener->received);
    }

    public function testRemoveObserverWithANameRemovesOnlyThatRegistration(): void
    {
        $center = new NotificationCenter();
        $listener = new SelectorListener();
        $center->addObserver($listener, "handleNotification", "primero");
        $center->addObserver($listener, "handleNotification", "segundo");

        $center->removeObserver($listener, "primero");
        $center->postNotificationName("primero");
        $center->postNotificationName("segundo");

        $this->assertSame(["segundo"], $listener->received);
    }

    public function testUserInfoIsCarriedThrough(): void
    {
        $center = new NotificationCenter();
        $carried = null;
        $center->addObserverForName("con-info", null, function (Notification $notification) use (&$carried): void {
            $carried = $notification->userInfo;
        });

        $center->postNotificationName("con-info", null, new Dictionary(["k" => "v"]));

        $this->assertInstanceOf(Dictionary::class, $carried);
        $this->assertSame("v", $carried["k"]);
    }

    public function testAnObserverBoundToAnObjectIgnoresOtherSenders(): void
    {
        $center = new NotificationCenter();
        $watched = new SelectorListener();
        $other = new SelectorListener();
        $fired = 0;
        $center->addObserverForName("filtrado", $watched, function () use (&$fired): void {
            $fired++;
        });

        $center->postNotificationName("filtrado", $other);
        $this->assertSame(0, $fired, "a different sender must not reach it");

        $center->postNotificationName("filtrado", $watched);
        $this->assertSame(1, $fired, "the sender it was bound to must reach it");
    }

    public function testAnObserverWithoutAnObjectAcceptsAnySender(): void
    {
        $center = new NotificationCenter();
        $sender = new SelectorListener();
        $fired = 0;
        $center->addObserverForName("cualquiera", null, function () use (&$fired): void {
            $fired++;
        });

        $center->postNotificationName("cualquiera", $sender);
        $center->postNotificationName("cualquiera", null);

        $this->assertSame(2, $fired);
    }

    public function testEveryObserverOfANameIsNotified(): void
    {
        $center = new NotificationCenter();
        $first = 0;
        $second = 0;
        $center->addObserverForName("multi", null, function () use (&$first): void {
            $first++;
        });
        $center->addObserverForName("multi", null, function () use (&$second): void {
            $second++;
        });

        $center->postNotificationName("multi");

        $this->assertSame(1, $first);
        $this->assertSame(1, $second);
    }

    public function testPostNotificationDeliversAPreparedNotification(): void
    {
        $center = new NotificationCenter();
        $name = null;
        $center->addObserverForName("directo", null, function (Notification $notification) use (&$name): void {
            $name = $notification->name;
        });

        $center->postNotification(new Notification("directo"));

        $this->assertSame("directo", $name);
    }

    public function testAnObserverMayUnregisterItselfWhileBeingNotified(): void
    {
        // The dispatch iterates a snapshot, so removing an entry mid-post must not make the loop skip the entry that follows it.
        $center = new NotificationCenter();
        $delivered = 0;
        $token = null;
        $token = $center->addObserverForName("reentrante", null, function () use (&$delivered, &$center, &$token): void {
            $delivered++;
            $center->removeObserver($token);
        });
        $center->addObserverForName("reentrante", null, function () use (&$delivered): void {
            $delivered++;
        });

        $center->postNotificationName("reentrante");

        $this->assertSame(2, $delivered);
    }

    public function testDefaultCenterIsShared(): void
    {
        $this->assertSame(NotificationCenter::default(), NotificationCenter::default());
    }
}
