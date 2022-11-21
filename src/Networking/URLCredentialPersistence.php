<?php

namespace Sabatier\Foundation\Networking;

/**
 * Constants that specify how long the credential will be kept.
 */
enum URLCredentialPersistence: int
{
    /** The credential should not be stored. */
    case none = 0;
    /** The credential should be stored only for this session. */
    case forSession = 1;
    /** The credential should be stored in the keychain. */
    case permanent = 2;
    /** The credential should be stored permanently in the keychain, and in addition should be distributed to other devices. */
    case synchronizable = 3;
}
