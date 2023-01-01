<?php

namespace Sabatier\Foundation\Networking;

use Sabatier\Foundation\ArrayClass;
use Sabatier\Foundation\Dictionary;

/**
 * A configuration object that defines behavior and policies for a URL session.
 * @psalm-consistent-constructor
 */
final class URLSessionConfiguration
{
    private static ?URLSessionConfiguration $default = null;
    /** @var Dictionary<string>|null A dictionary of additional headers to send with requests.
     * This property specifies additional headers that are added to all tasks within sessions based on this configuration. For example, you might set the User-Agent header so that it is automatically included in every request your app makes through sessions based on this configuration. */
    public ?Dictionary $httpAdditionalHeaders = null;
    /** @var float|int The timeout interval to use when waiting for additional data. */
    public float|int $timeoutIntervalForRequest = 60.0;
    /** @var HTTPCookieAcceptPolicy A policy constant that determines when cookies should be accepted. */
    public HTTPCookieAcceptPolicy $httpCookieAcceptPolicy = HTTPCookieAcceptPolicy::onlyFromMainDocumentDomain;
    /** @var bool A Boolean value that determines whether requests should contain cookies from the cookie store. This property controls whether tasks within sessions based on this configuration should automatically provide cookies from the shared cookie store when making requests. If you want to provide cookies yourself, set this value to false and provide a Cookie header either through the session's {@see URLSessionConfiguration::$httpAdditionalHeaders} property or on a per-request level using a custom {@see URLRequest} object. */
    public bool $httpShouldSetCookies = true;
    /** @var HTTPCookieStorage|null The cookie store for storing cookies within this session. This property determines the cookie storage object used by all tasks within sessions based on this configuration. To disable cookie storage, set this property to nil. For default and background sessions, the default value is the shared cookie storage object. */
    public ?HTTPCookieStorage $httpCookieStorage = null;
    /** @var URLCache|null The URL cache for providing cached responses to requests within the session. This property determines the URL cache object used by tasks within sessions based on this configuration. To disable caching, set this property to nil. For default sessions, the default value is the shared URL cache object. For background sessions, the default value is nil. For ephemeral sessions, the default value is a private cache object that stores data in memory only, and is destroyed when you invalidate the session. */
    public ?URLCache $urlCache = null;
    /** @var URLCredentialStorage|null A credential store that provides credentials for authentication. This property determines the credential storage object used by tasks within sessions based on this configuration. If you don't want to use a credential store, set this property to nil. For default and background sessions, the default value is the shared credential store object. For ephemeral sessions, the default value is a private credential store object that stores data in memory only, and is destroyed when you invalidate the session. */
    public ?URLCredentialStorage $urlCredentialStorage = null;
    /** @var ArrayClass<class-string<URLProtocol>>|null An array of extra protocol subclasses that handle requests in a session. */
    public ?ArrayClass $protocolClasses = null;
    /** @var int The maximum number of simultaneous connections to make to a given host. This property determines the maximum number of simultaneous connections made to each host by tasks within sessions based on this configuration. This limit is per session, so if you use multiple sessions, your app as a whole may exceed this limit. Additionally, depending on your connection to the Internet, a session may use a lower limit than the one you specify. */
    public int $httpMaximumConnectionsPerHost = 6;
    /** @var bool A Boolean value that determines whether the session should use HTTP pipelining. This property determines whether tasks within sessions based on this configuration should use HTTP pipelining. You can also enable pipelining on a per-task basis by creating the task with an {@see URLRequest} object. */
    public bool $httpShouldUsePipelining = false;
    /** @var Dictionary<mixed>|null A dictionary containing information about the proxy to use within this session. This property controls which proxy tasks within sessions based on this configuration use when connecting to remote hosts. The default value is NULL, which means that tasks use the default system settings. */
    public ?Dictionary $connectionProxyDictionary = null;

    public function __construct()
    {
        $this->httpCookieStorage = HTTPCookieStorage::shared();
        $this->urlCredentialStorage = URLCredentialStorage::shared();
        $this->urlCache = URLCache::shared();
    }

    /**
     * A default session configuration object.
     */
    public static function default(): URLSessionConfiguration
    {
        if (self::$default === null) {
            self::$default = new URLSessionConfiguration();
        }
        return self::$default;
    }

    /**
     * A session configuration that uses no persistent storage for caches, cookies, or credentials.
     */
    public static function ephemeral(): URLSessionConfiguration
    {
        $ephemeral = clone self::default();
        $ephemeral->httpCookieStorage = HTTPCookieStorage::ephemeralStorage();
        $ephemeral->urlCredentialStorage = new URLCredentialStorage(true);
        $ephemeral->urlCache = new URLCache(4 * 1024 * 1024, 0);
        return $ephemeral;
    }

    /** @internal */
    public function configure(URLRequest $request): URLRequest
    {
        if ($httpAdditionalHeaders = $this->httpAdditionalHeaders) {
            foreach ($httpAdditionalHeaders as $key => $value) {
                $request->setValueForHttpHeaderField($value, $key);
            }
        }
        if ($this->httpShouldSetCookies && ($cookies = $this->httpCookieStorage?->cookies($request->url))) {
            $cookiesHeaderFields = HTTPCookie::requestHeaderFields($cookies);
            if ($cookieValue = $cookiesHeaderFields["Cookie"]) {
                $request->setValueForHttpHeaderField($cookieValue, "Cookie");
            }
        }
        return $request;
    }
}
