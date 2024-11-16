<?php

namespace Sabatier\Foundation\Networking;

use Closure;
use Sabatier\Foundation\Dictionary;
use Sabatier\Foundation\NotificationCenter;
use Sabatier\Foundation\Number;
use Sabatier\Foundation\ObjectClass;

/**
 * The manager of a shared credentials cache.
 */
class URLCredentialStorage extends ObjectClass
{
    private static ?URLCredentialStorage $shared = null;
    /** @var Dictionary<Dictionary<URLCredential>> The dictionary has keys corresponding to the {@see URLProtectionSpace} instances. The values are dictionaries where the keys are username strings, and each value is the corresponding {@see URLCredential} instances. */
    private(set) Dictionary $allCredentials;
    /** @var Dictionary<URLCredential> */
    private Dictionary $defaultCredentials;

    public function __construct(public readonly bool $isEphemeral = false)
    {
        $this->allCredentials = new Dictionary();
        $this->defaultCredentials = new Dictionary();
    }

    /**
     * The shared URL credential storage instance.
     */
    public static function shared(): URLCredentialStorage
    {
        static::$shared ??= new URLCredentialStorage();
        return static::$shared;
    }

    /**
     * Returns the default credential for the specified protection space.
     *
     * If you override this method, also override {@see getDefaultCredential()}.
     * @param URLProtectionSpace $space The URL protection space of interest.
     * @return URLCredential|null The default credential for space or nil if no default has been set.
     */
    public function defaultCredential(URLProtectionSpace $space): ?URLCredential
    {
        return $this->defaultCredentials[(string)$space];
    }

    /**
     * Gets the default credential for the specified protection space, which is being accessed by the given task, and passes it to the provided completion handler.
     * @param URLProtectionSpace $space The protection space of interest.
     * @param URLSessionTask $task The task seeking to use the protection space
     * @param Closure(?URLCredential): void $completionHandler A completion handler that receives the default credential as its argument, or nil if there is no default credential for this combination of protection space and task.
     */
    public function getDefaultCredential(/** @noinspection PhpUnusedParameterInspection */ URLProtectionSpace $space, URLSessionTask $task, Closure $completionHandler): void
    {
        $completionHandler($this->defaultCredential($space));
    }

    /**
     * Sets the default credential for a specified protection space.
     *
     * @param URLCredential $credential The URL credential to set as the default for space. If the receiver does not contain credential in the specified protection space it will be added.
     * @param URLProtectionSpace $space The protection space whose default credential is being set.
     * @param URLSessionTask|null $task The task accessing the specified protection space. Subclasses of URLCredentialStorage may use the request URL or other properties of this task to affect how the default credential is stored.
     */
    public function setDefaultCredential(/** @noinspection PhpUnusedParameterInspection */ URLCredential $credential, URLProtectionSpace $space, ?URLSessionTask $task): void
    {
        if ($credential->persistence === URLCredentialPersistence::synchronizable || $credential->persistence === URLCredentialPersistence::none || !$this->setWhileLocked($credential, $space, true)) {
            return;
        }
        $this->sendNotificationWhileUnlocked();
    }

    /**
     * Removes the specified credential from the credential storage for the specified protection space, on behalf of the given task and using the given options.
     *
     * The credential is removed from both persistent and temporary storage.
     * @param URLCredential $credential The credential to remove.
     * @param URLProtectionSpace|string $space The protection space from which to remove the credential.
     * @param Dictionary|null $options A dictionary containing options to consider when removing the credential.
     * @param URLSessionTask|null $task The task using the protection space that you wish to remove the credential for.
     */
    public function remove(/** @noinspection PhpUnusedParameterInspection */ URLCredential $credential, URLProtectionSpace|string $space, ?Dictionary $options = null, ?URLSessionTask $task = null): void
    {
        if (($credential->persistence === URLCredentialPersistence::synchronizable) && (!($removeSynchronizable = $options?->valueForKey(URLCredentialStorageRemoveSynchronizableCredentials)) || !$removeSynchronizable instanceof Number || !$removeSynchronizable->boolValue)) {
            return;
        }
        $needsNotification = false;
        $key = (string)$space;
        if (($user = $credential->user) && ($current = $this->allCredentials[$key]) && $current[$user] === $credential) {
            $current[$user] = null;
            $needsNotification = true;
            if ($current->isEmpty) {
                $current = null;
            }
            $this->allCredentials[$key] = $current;
        }
        if (($defaultCredential = $this->defaultCredentials[$key]) && $defaultCredential === $credential) {
            $this->defaultCredentials->removeValueForKey($key);
            $needsNotification = true;
        }
        if ($needsNotification) {
            $this->sendNotificationWhileUnlocked();
        }
    }

    /**
     * Adds a credential to the credential storage for the specified protection space, on behalf of the specified task.
     *
     * @param URLCredential $credential The credential to add. If a credential with the same username already exists in space, then credential replaces the existing object.
     * @param URLProtectionSpace $space The protection space to which to add the credential.
     * @param URLSessionTask|null $task The task accessing the specified protection space. Subclasses of URLCredentialStorage may use the request URL or other properties of this task to affect how the default credential is stored.
     */
    public function set(/** @noinspection PhpUnusedParameterInspection */ URLCredential $credential, URLProtectionSpace $space, ?URLSessionTask $task): void
    {
        if ($credential->persistence === URLCredentialPersistence::synchronizable || $credential->persistence === URLCredentialPersistence::none || !$this->setWhileLocked($credential, $space)) {
            return;
        }
        $this->sendNotificationWhileUnlocked();
    }

    /**
     * Returns a dictionary containing the credentials for the specified protection space.
     *
     * @param URLProtectionSpace $space The protection space whose credentials you want to retrieve.
     * @return Dictionary<URLCredential>|null A dictionary containing the credentials for the specified protection space. The dictionary's keys are username strings, and each value is the corresponding {@see URLCredential}.
     */
    public function credentials(URLProtectionSpace $space): ?Dictionary
    {
        return $this->allCredentials[(string)$space];
    }

    /**
     * Returns a dictionary containing the credentials for the specified protection space.
     *
     * @param URLProtectionSpace $space The protection space whose credentials you want to retrieve.
     * @param URLSessionTask|null $task The task accessing the specified protection space.
     * @param Closure(Dictionary<URLCredential>|null): void $completionHandler A completion handler that receives a single argument with the credentials for the specified protection space and task. The dictionary's keys are username strings, and the corresponding value is a URLCredential. If no credential has been set for this space, the argument to the completion handler is nil.
     */
    public function getCredentials(/** @noinspection PhpUnusedParameterInspection */ URLProtectionSpace $space, ?URLSessionTask $task, Closure $completionHandler): void
    {
        $completionHandler($this->credentials($space));
    }

    private function setWhileLocked(URLCredential $credential, URLProtectionSpace $space, bool $isDefault = false): bool
    {
        $modified = false;
        $key = (string)$space;
        if ($user = $credential->user) {
            /** @var Dictionary<URLCredential> $current */
            $current = $this->allCredentials[$key] ?? new Dictionary();
            $modified = $current[$user] !== $credential;
            $current[$user] = $credential;
            $this->allCredentials[$key] = $current;
        }
        if ($isDefault || $this->defaultCredentials[$key] === null) {
            $modified = $modified || $this->defaultCredentials[$key] !== $credential;
            $this->defaultCredentials[$key] = $credential;
        }
        return $modified;
    }

    private function sendNotificationWhileUnlocked(): void
    {
        NotificationCenter::default()->postNotificationName(URLCredentialStorageChangedNotification, $this);
    }
}
