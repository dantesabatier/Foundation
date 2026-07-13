<?php

declare(strict_types=1);

namespace Sabatier\Foundation\Networking;

use SensitiveParameter;

/**
 * An authentication credential consisting of information specific to the type of credential and the type of persistent storage to use, if any.
 */
final readonly class URLCredential
{
    /** @var bool A Boolean value that indicates whether the credential has a password. */
    public bool $hasPassword;

    /**
     * Creates a URL credential instance initialized with a given username and password, using a given persistence setting.
     * @param string $user The user for the credential.
     * @param string|null $password The password for $user.
     * @param URLCredentialPersistence $persistence A {@see URLCredentialPersistence} value indicating whether the credential should be stored permanently, for the duration of the current session or not at all.
     */
    public function __construct(public string $user, #[SensitiveParameter] public ?string $password = null, public URLCredentialPersistence $persistence = URLCredentialPersistence::none)
    {
        $this->hasPassword = $this->password !== null;
    }
}
