<?php

namespace Sabatier\Foundation;

/**
 * Class URLSessionConfiguration
 * A configuration object that defines behavior and policies for a URL session.
 * @package Sabatier\Foundation
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
    /** @var URLCredentialStorage|null A credential store that provides credentials for authentication. This property determines the credential storage object used by tasks within sessions based on this configuration. If you don’t want to use a credential store, set this property to nil. For default and background sessions, the default value is the shared credential store object. For ephemeral sessions, the default value is a private credential store object that stores data in memory only, and is destroyed when you invalidate the session. */
    public ?URLCredentialStorage $urlCredentialStorage = null;

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
}
