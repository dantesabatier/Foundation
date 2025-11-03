<?php

namespace Sabatier\Foundation\Networking;

use Sabatier\Foundation\ArrayClass;
use Sabatier\Foundation\ObjectClass;
use function Sabatier\Foundation\fatal_error;
use function Sabatier\Foundation\request_concrete_implementation;

/**
 * An abstract class that handles the loading of protocol-specific URL data.
 * @psalm-consistent-constructor
 */
abstract class URLProtocol extends ObjectClass
{
    /** @var ArrayClass<class-string<URLProtocol>>|null */
    private static ?ArrayClass $registeredProtocolClasses = null;
    /** @var CachedURLResponse|null The protocol's cached response. If not overridden in a subclass, this method returns the cached response stored at initialization time. */
    public readonly ?CachedURLResponse $cachedResponse;
    /** @var URLProtocolClient|null The object the protocol uses to communicate with the URL loading system. */
    public readonly ?URLProtocolClient $client;
    /** @var URLSessionTask The protocol's task. */
    public readonly URLSessionTask $task;
    /** @var URLRequest The protocol's request. */
    public readonly URLRequest $request;

    /**
     * Creates a URL protocol instance to handle the request.
     *
     * @param URLSessionTask $task A task containing a URL request to be performed by the protocol.
     * @param CachedURLResponse|null $cachedResponse A cached response for the request; it may be nil if there is no existing cached response for the request.
     * @param URLProtocolClient|null $client An object that provides an implementation of the {@see URLProtocolClient} protocol that this instance uses to communicate with the URL Loading System. This client object is retained.
     */
    public function __construct(URLSessionTask $task, ?CachedURLResponse $cachedResponse = null, ?URLProtocolClient $client = null)
    {
        $this->task = $task;
        $this->cachedResponse = $cachedResponse;
        $this->request = $task->originalRequest ?? fatal_error("A protocol class was requested, but we do not have a request");
        $this->client = $client ?? new ProtocolClient();
    }

    /**
     * @return ArrayClass<class-string<URLProtocol>>
     */
    private static function registeredProtocolClasses(): ArrayClass
    {
        self::$registeredProtocolClasses ??= new ArrayClass();
        return self::$registeredProtocolClasses;
    }

    /**
     * Attempts to register a subclass of URLProtocol, making it visible to the URL loading system.
     *
     * Register any custom URLProtocol subclasses prior to making URL requests. When the URL loading system begins to load a request, it tries to initialize each registered protocol class with the specified request. The first URLProtocol subclass to return true when sent a {@see canInit()} message is used to load the request. There is no guarantee that all registered protocol classes will be consulted.
     * Classes are consulted in the reverse order of their registration. A similar design governs the process to create the canonical form of a request with {@see canonicalRequest()}.
     * @param class-string<URLProtocol> $protocolClass The subclass to register.
     * @return bool true if the registration is successful, false otherwise. The only failure condition is if protocolClass is not a subclass of URLProtocol.
     */
    public static function registerClass(string $protocolClass): bool
    {
        if (!is_subclass_of($protocolClass, URLProtocol::class)) {
            return false;
        }
        $registeredProtocolClasses = self::registeredProtocolClasses();
        if (!$registeredProtocolClasses->containsElement($protocolClass)) {
            $registeredProtocolClasses[] = $protocolClass;
        }
        return true;
    }

    /**
     * @param ArrayClass<class-string<URLProtocol>> $protocolClasses
     * @param URLRequest $request
     * @return class-string<URLProtocol>|null
     * @internal
     */
    public static function getProtocolClass(ArrayClass $protocolClasses, URLRequest $request): ?string
    {
        return $protocolClasses->first(fn(mixed $protocolClass) => $protocolClass::canInit($request));
    }

    /**
     * @return ArrayClass<class-string<URLProtocol>>|null
     * @internal
     */
    public static function getProtocols(): ?ArrayClass
    {
        return self::$registeredProtocolClasses;
    }

    /**
     * Unregisters the specified subclass of URLProtocol.
     *
     * After this method is invoked, protocolClass is no longer consulted by the URL loading system.
     * @param class-string<URLProtocol> $protocolClass The subclass of URLProtocol to unregister.
     */
    public function unregisterClass(string $protocolClass): void
    {
        if ($registeredProtocolClasses = self::$registeredProtocolClasses) {
            $registeredProtocolClasses->remove($protocolClass);
        }
    }

    /**
     * Determines whether the protocol subclass can handle the specified request.
     *
     * A subclass should inspect request and determine whether the implementation can perform a load with that request. This is an abstract method and subclasses must provide an implementation.
     * @param URLRequest $request The request to be handled.
     * @return bool true if the protocol subclass can handle request, otherwise false.
     */
    public static function canInit(URLRequest $request): bool
    {
        request_concrete_implementation(static::class, __FUNCTION__);
    }

    /**
     * Fetches the property associated with the specified key in the specified request.
     *
     * Use this method to access protocol-specific information associated with {@see URLRequest} objects.
     * @param string $key The key of the desired property.
     * @param URLRequest $request The request whose properties are to be queried.
     * @return mixed The property associated with key, or nil if no property has been stored for key.
     */
    public static function property(string $key, URLRequest $request): mixed
    {
        return $request->protocolProperties[$key];
    }

    /**
     * Sets the property associated with the specified key in the specified request.
     *
     * Use this method to provide an interface for protocol implementors to customize protocol-specific information associated with {@see URLRequest} objects.
     * @param mixed $value The value to set for the specified property.
     * @param string $key The key for the specified property.
     * @param URLRequest $request The request for which to create the property.
     */
    public static function setProperty(mixed $value, string $key, URLRequest $request): void
    {
        $request->protocolProperties[$key] = $value;
    }

    /**
     * Removes the property associated with the specified key in the specified request.
     *
     * @param string $key The key whose value should be removed.
     * @param URLRequest $request The request from which to remove the property value.
     * This method is used to provide an interface for protocol implementors to customize protocol-specific information associated with {@see URLRequest} objects.
     */
    public static function removeProperty(string $key, URLRequest $request): void
    {
        $request->protocolProperties->removeValueForKey($key);
    }

    /**
     * Returns a canonical version of the specified request.
     *
     * It is up to each concrete protocol implementation to define what "canonical" means. A protocol should guarantee that the same input request always yields the same canonical form.
     * Special consideration should be given when implementing this method, because the canonical form of a request is used to lookup objects in the URL cache, a process which performs equality checks between URLRequest instances.
     * This is an abstract method and subclasses must provide an implementation.
     * @param URLRequest $request The request whose canonical version is desired.
     * @return URLRequest The canonical form of request.
     */
    public static function canonicalRequest(URLRequest $request): URLRequest
    {
        request_concrete_implementation(static::class, __FUNCTION__);
    }

    /**
     * A Boolean value indicating whether two requests are equivalent for cache purposes.
     *
     * Requests are considered equivalent for cache purposes if and only if they would be handled by the same protocol and that protocol declares them equivalent after performing implementation-specific checks.
     * The URLProtocol implementation of this method compares the URLs of the requests to determine if the requests should be considered equivalent. Subclasses can override this method to provide protocol-specific comparisons.
     * @param URLRequest $a The request to compare with bRequest.
     * @param URLRequest $b The request to compare with aRequest.
     * @return bool true if aRequest and bRequest are equivalent for cache purposes, false otherwise.
     */
    public static function requestIsCacheEquivalent(URLRequest $a, URLRequest $b): bool
    {
        return $a->url->isEqual($b->url);
    }

    /**
     * Starts protocol-specific loading of the request.
     *
     * When this method is called, the subclass implementation should start loading the request, providing feedback to the URL loading system via the {@see URLProtocolClient} protocol.
     * Subclasses must implement this method.
     */
    abstract public function startLoading(): void;

    /**
     * Stops protocol-specific loading of the request.
     *
     * When this method is called, the subclass implementation should stop loading a request. This could be in response to a cancel operation, so protocol implementations must be able to handle this call while a load is in progress. When your protocol receives a call to this method, it should also stop sending notifications to the client.
     * Subclasses must implement this method.
     */
    abstract public function stopLoading(): void;
}
