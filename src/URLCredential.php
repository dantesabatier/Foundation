<?php

namespace Sabatier\Foundation;

use JetBrains\PhpStorm\Immutable;

/**
 * Class URLCredential
 * An authentication credential consisting of information specific to the type of credential and the type of persistent storage to use, if any.
 * @package Sabatier\Foundation
 */
#[Immutable]
class URLCredential
{
    /**
     * Creates a URL credential instance initialized with a given username and password, using a given persistence setting.
     * @param string $user The user for the credential.
     * @param string|null $password The password for user.
     * @param URLCredentialPersistence $persistence A {@see URLCredentialPersistence} value indicating whether the credential should be stored permanently, for the duration of the current session, or not at all.
     */
    public function __construct(public readonly string $user, public readonly ?string $password = null, public readonly URLCredentialPersistence $persistence = URLCredentialPersistence::none)
    {
    }
}
