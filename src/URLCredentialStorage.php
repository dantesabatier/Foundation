<?php

namespace Sabatier\Foundation;

use Closure;

/**
 * Class URLCredentialStorage
 * The manager of a shared credentials cache.
 * @package Sabatier\Foundation
 */
class URLCredentialStorage
{
    private static ?URLCredentialStorage $shared = null;

    /**
     * The shared URL credential storage instance.
     * @return URLCredentialStorage
     */
    public static function shared(): URLCredentialStorage
    {
        if (static::$shared === null) {
            static::$shared = new URLCredentialStorage();
        }
        return static::$shared;
    }

    /**
     * Returns the default credential for the specified protection space.
     * If you override this method, also override getDefaultCredential().
     * @param URLProtectionSpace $space The URL protection space of interest.
     * @return URLCredential|null The default credential for space or nil if no default has been set.
     */
    public function defaultCredential(/** @noinspection PhpUnusedParameterInspection */ URLProtectionSpace $space): ?URLCredential
    {
        return null;
    }

    /**
     * Gets the default credential for the specified protection space, which is being accessed by the given task, and passes it to the provided completion handler.
     * @param URLProtectionSpace $space The protection space of interest.
     * @param URLSessionTask $task The task seeking to use the protection space
     * @param Closure(?URLCredential): void $completionHandler A completion handler that receives the default credential as its argument, or nil if there is no default credential for this combination of protection space and task.
     */
    public function getDefaultCredential(URLProtectionSpace $space, URLSessionTask $task, Closure $completionHandler): void
    {
    }

    /**
     * Sets the default credential for a specified protection space.
     * @param URLCredential $credential The URL credential to set as the default for space. If the receiver does not contain credential in the specified protection space it will be added.
     * @param URLProtectionSpace $space The protection space whose default credential is being set.
     * @param URLSessionTask|null $task The task accessing the specified protection space. Subclasses of URLCredentialStorage may use the request URL or other properties of this task to affect how the default credential is stored.
     */
    public function setDefaultCredential(URLCredential $credential, URLProtectionSpace $space, ?URLSessionTask $task): void
    {
    }
}
