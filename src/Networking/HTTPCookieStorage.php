<?php

namespace Sabatier\Foundation\Networking;

use Closure;
use Exception;
use Override;
use Sabatier\Foundation\ArrayClass;
use Sabatier\Foundation\Bundle;
use Sabatier\Foundation\CompareOptions;
use Sabatier\Foundation\Date;
use Sabatier\Foundation\Dictionary;
use Sabatier\Foundation\FileManager;
use Sabatier\Foundation\ObjectClass;
use Sabatier\Foundation\PropertyListSerialization;
use Sabatier\Foundation\SearchPathDirectory;
use Sabatier\Foundation\SortDescriptor;
use Sabatier\Foundation\URL;
use function Sabatier\Foundation\string_has_suffix;
use function Sabatier\Foundation\string_is_equal;
use const Sabatier\Foundation\kCFBundleNameKey;

/**
 * A container that manages the storage of cookies.
 */
final class HTTPCookieStorage extends ObjectClass
{
    /** @var Dictionary<HTTPCookieStorage>|null */
    private static ?Dictionary $sharedCookieStorages = null;
    /** @var HTTPCookieAcceptPolicy The cookie storage's cookie accept policy. */
    public HTTPCookieAcceptPolicy $cookieAcceptPolicy = HTTPCookieAcceptPolicy::always;
    /** @var Dictionary<HTTPCookie> */
    private Dictionary $allCookies;
    private ?URL $cookieFileURL = null;
    /** @var ArrayClass<HTTPCookie> $cookies */
    public ArrayClass $cookies {
        get => $this->allCookies->values;
    }
    #[Override]
    public string $description {
        get => ($this->isEphemeral ? "Ephemeral" : "") . "<HTTPCookieStorage cookies count:({$this->allCookies->count})>";
    }

    final private function __construct(string $cookieStorageName, private readonly bool $isEphemeral = false)
    {
        $this->allCookies = new Dictionary();
        if (!$this->isEphemeral) {
            try {
                $bundle = Bundle::main();
                $bundleName = $bundle->object(kCFBundleNameKey) ?? $bundle->bundleURL->deletingPathExtension()->lastPathComponent;
                $cookieFolderURL = new URL($bundleName, FileManager::default()->url(SearchPathDirectory::applicationSupportDirectory));
                $this->cookieFileURL = $this->fileURL($cookieFolderURL, ".cookies.$cookieStorageName", $bundleName);
                $this->loadPersistedCookies();
            } catch (Exception) {
            }
        }
    }

    /**
     * @return Dictionary<HTTPCookieStorage>
     */
    private static function sharedCookieStorages(): Dictionary
    {
        self::$sharedCookieStorages ??= new Dictionary();
        return self::$sharedCookieStorages;
    }

    /**
     * The shared cookie storage instance.
     *
     * @return HTTPCookieStorage
     */
    public static function shared(): HTTPCookieStorage
    {
        $sharedCookieStorages = self::sharedCookieStorages();
        if (!($shared = $sharedCookieStorages["shared"])) {
            $shared = new HTTPCookieStorage("shared");
            $sharedCookieStorages["shared"] = $shared;
        }
        return $shared;
    }

    /** @internal */
    public static function ephemeralStorage(): HTTPCookieStorage
    {
        return new HTTPCookieStorage("Ephemeral", true);
    }

    private function directoryURL(URL $directoryURL): bool
    {
        if (FileManager::default()->fileExists($directoryURL->path)) {
            return true;
        }
        try {
            FileManager::default()->createDirectory($directoryURL, true);
            return true;
        } catch (Exception) {
            return false;
        }
    }

    private function fileURL(URL $url, string $fileName, string $bundleName): URL
    {
        if ($this->directoryURL($url)) {
            return $url->appendingPathComponent($fileName);
        }
        return FileManager::default()->documentRootDirectory->appendingPathComponent($bundleName)->appendingPathComponent($fileName);
    }

    private function createCookie(Dictionary $properties): HTTPCookie
    {
        /** @var Dictionary<mixed> $cookieProperties */
        $cookieProperties = new Dictionary();
        foreach ($properties as $key => $value) {
            if ($key === HTTPCookiePropertyKey::expires) {
                if (is_numeric($value)) {
                    $cookieProperties[$key] = Date::dateWithTimeIntervalSince1970((float)$value);
                }
            } else {
                $cookieProperties[$key] = $value;
            }
        }
        return new HTTPCookie($cookieProperties);
    }

    private function loadPersistedCookies(): void
    {
        if (!($cookieFileURL = $this->cookieFileURL)) {
            return;
        }
        /** @var Dictionary<Dictionary<mixed>> $cookies */
        $cookies = PropertyListSerialization::propertyListWithURL($cookieFileURL) ?? new Dictionary();
        foreach ($cookies as $key => $value) {
            $this->allCookies[$key] = $this->createCookie($value);
        }
    }

    private function updatePersistentStore(): void
    {
        if ($this->isEphemeral || !($cookieFileURL = $this->cookieFileURL)) {
            return;
        }
        $persistent = $this->allCookies->filter(fn(HTTPCookie $cookie): bool => $cookie->expiresDate !== null && $cookie->expiresDate->timeIntervalSinceNow > 0 && !$cookie->isSessionOnly);
        /** @var Dictionary<Dictionary<mixed>> $persistDictionary */
        $persistDictionary = $persistent->reduce(new Dictionary(), function (Dictionary $result, HTTPCookie $cookie, string $key): Dictionary {
            $result[$key] = $cookie->properties;
            return $result;
        });
        PropertyListSerialization::writePropertyList($persistDictionary, $cookieFileURL);
    }

    /**
     * Removes cookies stored after a given date.
     *
     * @param Date $date The date after which cookies should be removed.
     */
    public function removeCookies(Date $date): void
    {
        $this->allCookies->removeAll(fn(HTTPCookie $cookie): bool => ($expiresDate = $cookie->expiresDate) && $expiresDate->timeIntervalSinceNow > $date->timeIntervalSinceReferenceDate);
        $this->updatePersistentStore();
    }

    /**
     * Deletes the specified cookie from the cookie storage.
     *
     * @param HTTPCookie $cookie The cookie to delete.
     */
    public function deleteCookie(HTTPCookie $cookie): void
    {
        $this->allCookies->removeValueForKey("$cookie->domain$cookie->path$cookie->name");
        $this->updatePersistentStore();
    }

    /**
     * Stores a specified cookie in the cookie storage if the cookie accept policy permits.
     *
     * The cookie replaces an existing cookie with the same name, domain, and path, if one exists in the cookie storage. This method accepts the cookie only if the storage's cookie accept policy is HTTPCookieAcceptPolicy::always or HTTPCookieAcceptPolicy::onlyFromMainDocumentDomain. The cookie is ignored if the storage's cookie accept policy is HTTPCookieAcceptPolicy::never.
     *
     * @param HTTPCookie $cookie The cookie to store.
     */
    public function setCookie(HTTPCookie $cookie): void
    {
        if ($this->cookieAcceptPolicy === HTTPCookieAcceptPolicy::never) {
            return;
        }
        $key = "$cookie->domain$cookie->path$cookie->name";
        if ($this->allCookies[$key]) {
            $this->allCookies->updateValue($cookie, $key);
        } else {
            $this->allCookies[$key] = $cookie;
        }
        $this->allCookies->removeAll(fn(HTTPCookie $cookie): bool => ($expiresDate = $cookie->expiresDate) && $expiresDate->timeIntervalSinceNow < 0);
        $this->updatePersistentStore();
    }

    /**
     * Adds an array of cookies to the cookie storage if the storage's cookie acceptance policy permits.
     *
     * Cookies in the array will replace existing cookies with the same name, domain, and path in the cookie storage. If the storage has an acceptance policy of HTTPCookie.AcceptPolicy.never, the cookies are ignored.
     * To store cookies from a set of response headers, an application can use cookies({@see HTTPCookie::cookies()}) passing a header field dictionary and then use this method to store the resulting cookies in accordance with the cookie storage's cookie acceptance policy.
     * If you override this method, also override {@see HTTPCookieStorage::storeCookies()}.
     *
     * @param ArrayClass<HTTPCookie> $cookies The cookies to add.
     * @param URL|null $url The URL associated with the added cookies.
     * @param URL|null $mainDocumentURL The URL of the main HTML document for the top-level frame, if known. The value can be null. This URL is used to determine whether the cookie should be accepted if the cookie accept policy is HTTPCookieAcceptPolicy::onlyFromMainDocumentDomain.
     */
    public function setCookies(ArrayClass $cookies, ?URL $url = null, ?URL $mainDocumentURL = null): void
    {
        if ($this->cookieAcceptPolicy === HTTPCookieAcceptPolicy::never || !($host = $url?->host)) {
            return;
        }
        if ($this->cookieAcceptPolicy === HTTPCookieAcceptPolicy::onlyFromMainDocumentDomain && (!($documentHost = $mainDocumentURL?->host) || !string_has_suffix($documentHost, $host, CompareOptions::caseInsensitive))) {
            return;
        }
        $cookies = $cookies->filter(fn(HTTPCookie $cookie): bool => str_starts_with($cookie->domain, ".") ? string_has_suffix($host, $cookie->domain, CompareOptions::caseInsensitive) : string_is_equal($cookie->domain, $host, CompareOptions::caseInsensitive));
        $cookies->forEach(fn(HTTPCookie $cookie) => $this->setCookie($cookie));
    }

    /**
     * Stores an array of cookies in the cookie storage, on behalf of the provided task if the cookie accept policy permits.
     *
     * @param ArrayClass<HTTPCookie> $cookies The cookies to add.
     * @param URLSessionTask $task The task that handles the response. Override this method and inspect this parameter if you need to alter your cookie storage strategy based on properties of the task.
     */
    public function storeCookies(ArrayClass $cookies, URLSessionTask $task): void
    {
        $this->setCookies($cookies, $task->originalRequest?->url, $task->originalRequest?->mainDocumentURL);
    }

    /**
     * Fetches cookies relevant to the specified task and passes them to the completion handler.
     *
     * @param URLSessionTask $task The task performing a request. The cookie storage can use the URL and other properties of this task's request to determine which cookies to fetch.
     * @param Closure(ArrayClass<HTTPCookie>|null): void $completionHandler A completion handler that receives an array of cookies as its argument.
     */
    public function getCookiesFor(URLSessionTask $task, Closure $completionHandler): void
    {
        if (!($request = $task->originalRequest)) {
            $completionHandler(null);
            return;
        }
        $completionHandler($this->cookies($request->url));
    }

    /**
     * Returns all the cookie storage's cookies that are sent to a specified URL.
     *
     * You can use the {@see HTTPCookie::requestHeaderFields()} method of HTTPCookie to turn the array returned by this method into a set of header fields to add to a URLRequest object.
     * If you override this method, also override {@see getCookiesFor()}.
     *
     * @param URL $url The URL to filter on.
     * @return ArrayClass<HTTPCookie>|null An array of cookies whose URL matches the provided URL.
     */
    public function cookies(URL $url): ?ArrayClass
    {
        if (!($host = $url->host)) {
            return null;
        }
        return $this->allCookies->filter(fn(HTTPCookie $cookie): bool => str_starts_with($cookie->domain, ".") ? string_has_suffix($host, $cookie->domain, CompareOptions::caseInsensitive) : string_is_equal($cookie->domain, $host, CompareOptions::caseInsensitive))->values;
    }

    /**
     * Returns all the cookie storage's cookies, sorted according to a given set of sort descriptors.
     *
     * @param ArrayClass<SortDescriptor> $sortOrder The sort descriptors to use for sorting, as an array of {@see SortDescriptor} objects.
     * @return ArrayClass<HTTPCookie> The cookie storage's cookies, sorted according to sortOrder, as an array of {@see HTTPCookie} objects.
     */
    public function sortedCookies(ArrayClass $sortOrder): ArrayClass
    {
        return $this->allCookies->values->sorted($sortOrder);
    }
}
