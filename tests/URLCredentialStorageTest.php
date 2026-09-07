<?php

declare(strict_types=1);

namespace Sabatier\Foundation\Tests;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Sabatier\Foundation\Networking\URLCredential;
use Sabatier\Foundation\Networking\URLCredentialPersistence;
use Sabatier\Foundation\Networking\URLCredentialStorage;
use Sabatier\Foundation\Networking\URLProtectionSpace;
use Sabatier\Foundation\NotificationCenter;
use const Sabatier\Foundation\Networking\URLAuthenticationMethodHTTPBasic;
use const Sabatier\Foundation\Networking\URLCredentialStorageChangedNotification;

/**
 * Credentials must be indexed by protection-space values, not object identity, because each authentication response creates a new space.
 * Replacing a user's password must update its default credential; empty and zero usernames must survive storage and removal.
 */
final class URLCredentialStorageTest extends TestCase
{
    private function space(string $realm = "members"): URLProtectionSpace
    {
        return new URLProtectionSpace("example.com", 443, protocol: "https", realm: $realm, authenticationMethod: URLAuthenticationMethodHTTPBasic);
    }

    public function testEquivalentSpacesShareCredentialsAndOtherRealmsStayIsolated(): void
    {
        $storage = new URLCredentialStorage();
        $credential = new URLCredential("alice", "secret", URLCredentialPersistence::forSession);
        $original = $this->space();
        $equivalent = $this->space();
        $storage->set($credential, $original, null);
        $this->assertSame($credential, $storage->credentials($equivalent)?->valueForKey("alice"));
        $this->assertSame($credential, $storage->defaultCredential($equivalent));
        $this->assertNull($storage->credentials($this->space("admin")));
        $this->assertNull($storage->defaultCredential($this->space("admin")));
    }

    #[DataProvider("usernames")]
    public function testRemovalClearsTheDefaultAndTheEmptySpace(string $user): void
    {
        $storage = new URLCredentialStorage();
        $space = $this->space();
        $credential = new URLCredential($user, "secret", URLCredentialPersistence::forSession);
        $storage->set($credential, $space, null);
        $this->assertSame($credential, $storage->credentials($space)?->valueForKey($user));
        $storage->remove($credential, $space);
        $this->assertNull($storage->credentials($space));
        $this->assertNull($storage->defaultCredential($space));
        $this->assertTrue($storage->allCredentials->isEmpty);
    }

    public static function usernames(): array
    {
        return [["alice"], ["0"], [""]];
    }

    public function testReplacingTheDefaultUserReplacesItsDefaultCredential(): void
    {
        $storage = new URLCredentialStorage();
        $space = $this->space();
        $old = new URLCredential("alice", "old", URLCredentialPersistence::forSession);
        $new = new URLCredential("alice", "new", URLCredentialPersistence::forSession);
        $storage->set($old, $space, null);
        $storage->set($new, $space, null);
        $storage->remove($old, $space);
        $this->assertSame($new, $storage->credentials($space)?->valueForKey("alice"));
        $this->assertSame($new, $storage->defaultCredential($space));
    }

    public function testExplicitDefaultDoesNotChangeWhenAnotherUserIsAdded(): void
    {
        $storage = new URLCredentialStorage();
        $space = $this->space();
        $alice = new URLCredential("alice", "secret", URLCredentialPersistence::forSession);
        $bob = new URLCredential("bob", "secret", URLCredentialPersistence::forSession);
        $storage->setDefaultCredential($alice, $space, null);
        $storage->set($bob, $space, null);
        $this->assertSame($alice, $storage->defaultCredential($space));
        $this->assertSame(2, $storage->credentials($space)?->count);
        $storage->setDefaultCredential($bob, $space, null);
        $this->assertSame($bob, $storage->defaultCredential($space));
    }

    #[DataProvider("unstoredPersistence")]
    public function testUnsupportedPersistenceDoesNotEnterTheCache(URLCredentialPersistence $persistence): void
    {
        $storage = new URLCredentialStorage();
        $space = $this->space();
        $credential = new URLCredential("alice", "secret", $persistence);
        $storage->set($credential, $space, null);
        $storage->setDefaultCredential($credential, $space, null);
        $this->assertTrue($storage->allCredentials->isEmpty);
        $this->assertNull($storage->defaultCredential($space));
    }

    public static function unstoredPersistence(): array
    {
        return [[URLCredentialPersistence::none], [URLCredentialPersistence::synchronizable]];
    }

    public function testNotificationsOnlyDescribeActualChanges(): void
    {
        $storage = new URLCredentialStorage();
        $space = $this->space();
        $credential = new URLCredential("alice", "secret", URLCredentialPersistence::forSession);
        $calls = 0;
        $center = NotificationCenter::default();
        $observer = $center->addObserverForName(URLCredentialStorageChangedNotification, $storage, function () use (&$calls): void {
            $calls += 1;
        });
        try {
            $storage->set($credential, $space, null);
            $storage->set($credential, $space, null);
            $storage->remove($credential, $space);
            $storage->remove($credential, $space);
            $this->assertSame(2, $calls);
        } finally {
            $center->removeObserver($observer);
        }
    }
}
