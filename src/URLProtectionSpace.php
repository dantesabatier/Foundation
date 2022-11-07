<?php

namespace Sabatier\Foundation;

/**
 * Class URLProtectionSpace
 * A server or an area on a server, commonly referred to as a realm, that requires authentication.
 * @package Sabatier\Foundation
 */
class URLProtectionSpace
{
    /** @var bool A Boolean value that indicates whether the credentials for the protection space can be sent securely. This value is true if the credentials for the protection space represented by the receiver can be sent securely, false otherwise. */
    public bool $receivesCredentialSecurely = false;
    /** @var mixed A representation of the server’s SSL transaction state. This value is nil if the authentication method of the protection space is not server trust. */
    public mixed $serverTrust = null;

    /**
     * Creates a protection space object from the given host, port, protocol, realm, and authentication method.
     * @param string $host The host name for the URLProtectionSpace object.
     * @param int $port The port for the protection space object. If port is 0, the default port for the specified protocol is used, for example, port 80 for HTTP. Note that servers can, and do, treat these values differently.
     * @param string|null $protocol The protocol for the protection space object. The value of protocol is equivalent to the scheme for a URL in the protection space, for example, “http”, “https”, “ftp”, etc.
     * @param string|null $realm A string indicating a protocol-specific subdivision of the host. realm may be nil if there is no specified realm or if the protocol doesn't support realms.
     * @param string|null $authenticationMethod The type of authentication to use. authenticationMethod should be set to one of the values in URLProtectionSpace Authentication Method Constants or nil to use the default, {@see URLAuthenticationMethodDefault}.
     */
    public function __construct(public readonly string $host, public readonly int $port = 0, public readonly ?string $protocol = null, public readonly ?string $realm = null, public readonly ?string $authenticationMethod = null)
    {
    }
}
