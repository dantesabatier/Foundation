<?php

namespace Sabatier\Foundation\Networking;

use Sabatier\Foundation\ArrayClass;
use Sabatier\Foundation\Date;
use Sabatier\Foundation\Dictionary;
use Sabatier\Foundation\Number;
use Sabatier\Foundation\ObjectClass;
use Sabatier\Foundation\Scanner;
use Sabatier\Foundation\URL;
use function Sabatier\Foundation\fatal_error;
use function Sabatier\Foundation\human_readable_value;

/**
 * A representation of an HTTP cookie.
 *
 * An HTTPCookie object is immutable, initialized from a dictionary that contains the attributes of the cookie. This class supports two different cookie versions:
 * Version 0: The original cookie format defined by Netscape. Most cookies are in this format.
 * Version 1: The cookie format defined in RFC 6265, HTTP State Management Mechanism.
 */
final class HTTPCookie extends ObjectClass
{
    /** @var string The domain of the cookie. If the domain does not start with a dot, then the cookie is only sent to the exact host specified by the domain. If the domain does start with a dot, then the cookie is sent to other hosts in that domain as well, subject to certain restrictions. See RFC 6265 for more detail. */
    public readonly string $domain;
    /** @var string The cookie's path. The cookie will be sent with requests for this path in the cookie's domain, and all paths that have this prefix. A path of "/" means the cookie will be sent for all URLs in the domain. */
    public readonly string $path;
    /** @var ArrayClass<Number>|null The cookie's port list. The list of ports for the cookie, returned as an array of Number objects containing integers. If the cookie has no port list, the value of this property is null and the cookie will be sent to any port. Otherwise, the cookie is only sent to ports specified in the port list. */
    public readonly ?ArrayClass $portList;
    /** @var string The cookie's name. */
    public readonly string $name;
    /** @var string The cookie's string value. */
    public readonly string $value;
    /** @var int The cookie's version. Version 0 maps to "old-style" Netscape cookies. Version 1 maps to RFC 6265 cookies. */
    public readonly int $version;
    /** @var Date|null The cookie's expiration date. This value is null if there is no specific expiration date, as with session-only cookies. The expiration date is the date when the cookie should be deleted. */
    public readonly ?Date $expiresDate;
    /** @var bool A Boolean value that indicates whether the cookie should be discarded at the end of the session (regardless of expiration date). */
    public readonly bool $isSessionOnly;
    /** @var bool A Boolean value that indicates whether the cookie should only be sent to HTTP servers. */
    public readonly bool $isHTTPOnly;
    /** @var bool A Boolean value that indicates whether the cookie may only be sent over secure channels. */
    public readonly bool $isSecure;
    /** @var string|null Along with the policy values defined by {@see HTTPCookieStringPolicy}, this property may also be null. In this case, cross-site requests include the cookie. */
    public readonly ?string $sameSitePolicy;
    /** @var string|null The cookie's comment string. */
    public readonly ?string $comment;
    /** @var URL|null The cookie's comment URL. */
    public readonly ?URL $commentURL;
    /** @var Dictionary<mixed> The cookie's properties. */
    public readonly Dictionary $properties;
    public string $description {
        get => sprintf("<HTTPCookie version:%d name:\"%s\" value:\"%s\" expires:%s sessionOnly:%s domain:\"%s\" path:\"%s\" isSecure:%s comment:%s ports:{%s}", $this->version, $this->name, $this->value, human_readable_value($this->expiresDate), human_readable_value($this->isSessionOnly), $this->domain, $this->path, human_readable_value($this->isSecure), human_readable_value($this->comment), $this->portList?->join(",") ?? 0);
    }

    /**
     * Creates an HTTP cookie instance with the given cookie properties.
     * @param Dictionary<mixed> $properties The properties for the new cookie object, expressed as key-value pairs.
     */
    public function __construct(Dictionary $properties)
    {
        if (!($name = $properties[HTTPCookiePropertyKey::name]) || !($value = $properties[HTTPCookiePropertyKey::value]) || !($path = $properties[HTTPCookiePropertyKey::path])) {
            fatal_error();
        }
        /** @var string|null $domain */
        $domain = $properties[HTTPCookiePropertyKey::domain];
        if ($domain === null) {
            /** @var URL|null $originalURL */
            $originalURL = $properties[HTTPCookiePropertyKey::originURL];
            if ($originalURL instanceof URL) {
                $domain = $originalURL->host;
            }
        }
        $this->name = $name;
        $this->value = $value;
        $this->path = $path;
        $this->domain = $domain ?? fatal_error();
        $this->isSecure = !empty($properties[HTTPCookiePropertyKey::secure]);
        $this->version = (int)($properties[HTTPCookiePropertyKey::version] === 1);
        /** @var string|null $port */
        $port = $properties[HTTPCookiePropertyKey::port];
        if ($port !== null) {
            $portList = new ArrayClass(explode(",", $port))->map(fn(string $e): Number => new Number($e));
            if ($this->version === 1) {
                $this->portList = $portList;
            } else {
                $this->portList = $portList->isEmpty ? null : new ArrayClass([$portList[0]]);
            }
        } else {
            $this->portList = null;
        }
        $expiresDate = null;
        if ($maximumAge = $properties[HTTPCookiePropertyKey::maximumAge]) {
            $secondsFromNow = (float)$maximumAge;
            if ($this->version === 1) {
                $expiresDate = Date::dateWithTimeIntervalSinceNow($secondsFromNow);
            }
        } else {
            /** @var Date|string|null $expires */
            $expires = $properties[HTTPCookiePropertyKey::expires];
            if ($expires instanceof Date) {
                $expiresDate = $expires;
            } elseif ($expires) {
                $expiresDate = new Date((float)strtotime($expires));
            }
        }
        $this->expiresDate = $expiresDate;
        if ($discard = $properties[HTTPCookiePropertyKey::discard]) {
            $this->isSessionOnly = $discard === "TRUE";
        } else {
            $this->isSessionOnly = $properties[HTTPCookiePropertyKey::maximumAge] === null && $this->expiresDate === null && $this->version >= 1;
        }
        $this->comment = $properties[HTTPCookiePropertyKey::comment];
        /** @var URL|string|null $commentURL */
        $commentURL = $properties[HTTPCookiePropertyKey::commentURL];
        if ($commentURL instanceof URL) {
            $this->commentURL = $commentURL;
        } elseif ($commentURL) {
            $this->commentURL = new URL($commentURL);
        } else {
            $this->commentURL = null;
        }
        /** @var string|null $sameSitePolicy */
        $sameSitePolicy = $properties[HTTPCookiePropertyKey::sameSitePolicy];
        if ($sameSitePolicy === null && !$this->isSecure) {
            $sameSitePolicy = HTTPCookieStringPolicy::sameSiteLax;
        }
        $this->sameSitePolicy = $sameSitePolicy;
        $this->isHTTPOnly = $properties[HTTPCookiePropertyKey::httpOnly] === "TRUE";
        $this->properties = new Dictionary([
            HTTPCookiePropertyKey::created => new Date()->timeIntervalSinceReferenceDate,
            HTTPCookiePropertyKey::discard => $this->isSessionOnly,
            HTTPCookiePropertyKey::domain => $domain,
            HTTPCookiePropertyKey::name => $this->name,
            HTTPCookiePropertyKey::path => $this->path,
            HTTPCookiePropertyKey::secure => $this->isSecure,
            HTTPCookiePropertyKey::value => $this->value,
            HTTPCookiePropertyKey::version => $this->version,
            HTTPCookiePropertyKey::comment => $properties[HTTPCookiePropertyKey::comment],
            HTTPCookiePropertyKey::commentURL => $commentURL,
            HTTPCookiePropertyKey::expires => $properties[HTTPCookiePropertyKey::expires],
            HTTPCookiePropertyKey::maximumAge => $maximumAge,
            HTTPCookiePropertyKey::originURL => $properties[HTTPCookiePropertyKey::originURL],
            HTTPCookiePropertyKey::port => $this->portList,
            HTTPCookiePropertyKey::sameSitePolicy => $this->sameSitePolicy
        ]);
    }

    /**
     * @return array{0: string, 1: string|null}
     */
    private static function splitNameValue(string $pair): array
    {
        $components = explode("=", $pair, 2);
        $name = trim($components[0]);
        $value = null;
        if (count($components) > 1) {
            $value = trim($components[1]);
        }
        return [$name, $value];
    }

    /**
     * Creates an array of HTTP cookies that corresponds to the provided response header fields for the provided URL.
     *
     * This method ignores irrelevant header fields in headerFields, allowing dictionaries to contain additional data.
     * If $headerFields doesn't specify a domain for a given cookie, the cookie is created with a default domain value of URL.
     * If $headerFields doesn't specify a path for a given cookie, the cookie is created with a default path value of "/".
     * @param Dictionary<string> $headerFields The header fields used to create the HTTPCookie objects.
     * @param URL $url The URL associated with the created cookies.
     * @return ArrayClass<HTTPCookie> The array of created cookies.
     */
    public static function cookies(Dictionary $headerFields, URL $url): ArrayClass
    {
        if (!($cookies = $headerFields["Set-Cookie"])) {
            return new ArrayClass();
        }
        /** @var ArrayClass<HTTPCookie> $httpCookies */
        $httpCookies = new ArrayClass();
        $scanner = new Scanner($cookies);
        $scanner->charactersToBeSkipped = "\t\n\r";
        if ($scanner->scanUpString(";", $pair) && $pair) {
            $components = self::splitNameValue($pair);
            [$name, $value] = $components;
            /** @var Dictionary<mixed> $properties */
            $properties = new Dictionary();
            $properties[HTTPCookiePropertyKey::name] = $name;
            $properties[HTTPCookiePropertyKey::value] = $value;
            $properties[HTTPCookiePropertyKey::originURL] = $url;
            $scanner->scanLocation += 1;
            while ($scanner->scanUpCharacters(";", $pair) && $pair) {
                $components = self::splitNameValue($pair);
                [$name, $value] = $components;
                $name = ucwords($name);
                switch ($name) {
                    case HTTPCookiePropertyKey::secure:
                    case HTTPCookiePropertyKey::discard:
                    case HTTPCookiePropertyKey::httpOnly:
                    case HTTPCookiePropertyKey::sameSitePolicy:
                        $properties[$name] = "TRUE";
                        break;
                    case HTTPCookiePropertyKey::comment:
                    case HTTPCookiePropertyKey::commentURL:
                    case HTTPCookiePropertyKey::domain:
                    case HTTPCookiePropertyKey::maximumAge:
                    case HTTPCookiePropertyKey::path:
                    case HTTPCookiePropertyKey::port:
                    case HTTPCookiePropertyKey::version:
                    case HTTPCookiePropertyKey::expires:
                        $properties[$name] = $value;
                        break;
                    default:
                        break;
                }
                $scanner->scanLocation += 1;
            }
            $properties[HTTPCookiePropertyKey::version] ??= 1;
            /** @var string|null $domain */
            $domain = $properties[HTTPCookiePropertyKey::domain];
            if ($domain) {
                if (!str_starts_with($domain, ".") && filter_var($domain, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4)) {
                    $properties[HTTPCookiePropertyKey::domain] = ".$domain";
                }
            } else {
                $properties[HTTPCookiePropertyKey::domain] = $url->host;
            }
            /** @var string $domain */
            $domain = $properties[HTTPCookiePropertyKey::domain];
            if (!str_starts_with($domain, ".")) {
                $properties[HTTPCookiePropertyKey::domain] = strtolower($domain);
            }
            if (!($path = $properties[HTTPCookiePropertyKey::path]) || !str_starts_with((string)$path, "/")) {
                $properties[HTTPCookiePropertyKey::path] = "/";
            }
            $httpCookies->append(new HTTPCookie($properties));
        }
        return $httpCookies;
    }

    /**
     * Converts an array of cookies to a dictionary of header fields.
     *
     * To send these headers as part of a URL request to a remote server, create an {@see URLRequest} object, then call the {@see URLRequest::$allHTTPHeaderFields} or {@see URLRequest::setValueForHttpHeaderField()} method to set the provided headers for the request. Finally, initialize and start an {@see URLSessionTask} instance based on that request object.
     * @param ArrayClass<HTTPCookie> $cookies The cookies from which the header fields are created.
     * @return Dictionary<string> The dictionary of header fields created from the provided cookies.
     */
    public static function requestHeaderFields(ArrayClass $cookies): Dictionary
    {
        $cookieString = $cookies->reduce("", fn(string &$result, HTTPCookie $cookie): string => $result .= "$cookie->name=$cookie->value; ");
        if ($cookieString) {
            $cookieString = rtrim($cookieString, " ;");
        }
        /** @var Dictionary<string> $headerFields */
        $headerFields = new Dictionary();
        if (!empty($cookieString)) {
            $headerFields["Cookie"] = $cookieString;
        }
        return $headerFields;
    }
}
