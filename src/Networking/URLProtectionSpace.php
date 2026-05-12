<?php

namespace Sabatier\Foundation\Networking;

use Override;
use Sabatier\Foundation\ObjectClass;
use function Sabatier\Foundation\human_readable_value;

/**
 * A server or an area on a server, commonly referred to as a realm, that requires authentication.
 */
final class URLProtectionSpace extends ObjectClass
{
    final public const array authenticationMethods = [
        URLAuthenticationMethodDefault,
        URLAuthenticationMethodHTTPBasic,
        URLAuthenticationMethodHTTPDigest,
        URLAuthenticationMethodHTTPBearer,
        URLAuthenticationMethodHTMLForm,
        URLAuthenticationMethodNTLM,
        URLAuthenticationMethodNegotiate,
        URLAuthenticationMethodClientCertificate,
        URLAuthenticationMethodServerTrust,
    ];
    /** @var bool A Boolean value that indicates whether the credentials for the protection space can be sent securely. This value is true if the credentials for the protection space represented by the receiver can be sent securely, false otherwise. */
    public bool $receivesCredentialSecurely {
        get => match ($this->protocol) {
            URLProtectionSpaceHTTPS, "https", "ftps" => true,
            default => match ($this->authenticationMethod) {
                URLAuthenticationMethodNTLM, URLAuthenticationMethodNegotiate, URLAuthenticationMethodClientCertificate, URLAuthenticationMethodServerTrust => true,
                default => false
            }
        };
    }
    /** @var mixed A representation of the server's SSL transaction state. This value is null if the authentication method of the protection space is not server trust. */
    public mixed $serverTrust = null;
    public bool $isProxy {
        get => $this->proxyType !== null;
    }
    #[Override]
    public string $description {
        get => sprintf("<URLProtectionSpace %s>: Host:%s, Server:%s, Auth-Scheme:%s, Realm:%s, Port:%d, Proxy:%s, Proxy-Type:%s", $this->hash, $this->host, human_readable_value($this->protocol), in_array($this->authenticationMethod, self::authenticationMethods) ? $this->authenticationMethod : URLAuthenticationMethodDefault, human_readable_value($this->realm), $this->port, strtoupper(human_readable_value($this->isProxy)), human_readable_value($this->proxyType));
    }

    /**
     * Creates a protection space object from the given host, port, protocol, realm, and authentication method.
     * @param string $host The host name for the URLProtectionSpace object.
     * @param int $port The port for the protection space object. If port is 0, the default port for the specified protocol is used, for example, port 80 for HTTP. Note that servers can, and do, treat these values differently.
     * @param string|null $proxyType The type of proxy server. The value of proxyType should be set to one of the values specified in ProxyTypes.
     * @param string|null $protocol The protocol for the protection space object. The value of protocol is equivalent to the scheme for a URL in the protection space, for example, "http", "https", "ftp", etc.
     * @param string|null $realm A string indicating a protocol-specific subdivision of the host. realm may be null if there is no specified realm or if the protocol doesn't support realms.
     * @param string $authenticationMethod The type of authentication to use. authenticationMethod should be set to one of the values in URLProtectionSpace Authentication Method Constants.
     */
    public function __construct(public readonly string $host, public readonly int $port = 0, public readonly ?string $proxyType = null, public readonly ?string $protocol = null, public readonly ?string $realm = null, public readonly string $authenticationMethod = URLAuthenticationMethodDefault)
    {
    }

    public function __serialize(): array
    {
        return ["host" => $this->host, "port" => $this->port, "proxyType" => $this->proxyType, "protocol" => $this->protocol, "realm" => $this->realm, "authenticationMethod" => $this->authenticationMethod];
    }

    public function __unserialize(array $data): void
    {
        $this->host = $data["host"];
        $this->port = $data["port"];
        $this->proxyType = $data["proxyType"];
        $this->protocol = $data["protocol"];
        $this->realm = $data["realm"];
        $this->authenticationMethod = $data["authenticationMethod"];
    }

    /** @internal */
    public static function create(HTTPURLResponse $response): ?URLProtectionSpace
    {
        if (!($host = $response->url->host) || !($protocol = $response->url->scheme) || ($protocol !== "http" && $protocol !== "https") || !($challenge = Challenge::challenges($response)->first)) {
            return null;
        }
        $space = new URLProtectionSpace($host, $response->url->port ?? ($protocol === "http" ? 80 : 443), protocol: $protocol, realm: $challenge->parameter("realm"), authenticationMethod: $challenge->authenticationMethod ?? URLAuthenticationMethodDefault);
        $space->associatedValues["challenge"] = $challenge;
        return $space;
    }
}
