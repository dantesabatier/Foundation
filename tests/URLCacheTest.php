<?php

declare(strict_types=1);

namespace Sabatier\Foundation\Tests;

use Exception;
use Override;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Sabatier\Foundation\Date;
use Sabatier\Foundation\FileManager;
use Sabatier\Foundation\Networking\CachedURLResponse;
use Sabatier\Foundation\Networking\HTTPURLResponse;
use Sabatier\Foundation\Networking\URLCache;
use Sabatier\Foundation\Networking\URLCacheStoragePolicy;
use Sabatier\Foundation\Networking\URLRequest;
use Sabatier\Foundation\URL;

/**
 * Regression tests for cache identity, replacement, capacity accounting and storage policies.
 * Each cache uses a private temporary directory; a fresh instance verifies disk persistence independently of memory.
 */
final class URLCacheTest extends TestCase
{
    private URL $directory;
    private URLRequest $request;

    /** @throws Exception */
    #[Override]
    protected function setUp(): void
    {
        $this->directory = URL::fileURL(sys_get_temp_dir() . DIRECTORY_SEPARATOR . "sabatier-urlcache-" . bin2hex(random_bytes(8)));
        FileManager::default()->createDirectory($this->directory, true);
        $this->request = new URLRequest(new URL("https://example.com/item"));
    }

    /** @throws Exception */
    #[Override]
    protected function tearDown(): void
    {
        FileManager::default()->removeItem($this->directory);
    }

    private function response(string $data, URLCacheStoragePolicy $policy = URLCacheStoragePolicy::allowedInMemoryOnly): CachedURLResponse
    {
        return new CachedURLResponse(new HTTPURLResponse($this->request->url), $data, $policy);
    }

    public function testMemoryRoundTripAndRemoval(): void
    {
        $cache = new URLCache(100, 0, $this->directory);
        $response = $this->response("abc");
        $this->assertNull($cache->cachedResponse($this->request));
        $cache->storeCachedResponse($response, $this->request);
        $this->assertSame($response, $cache->cachedResponse($this->request));
        $this->assertSame(3, $cache->currentMemoryUsage);
        $cache->removeCachedResponse($this->request);
        $this->assertNull($cache->cachedResponse($this->request));
        $this->assertSame(0, $cache->currentMemoryUsage);
    }

    public function testExactMemoryCapacityAndEviction(): void
    {
        $cache = new URLCache(6, 0, $this->directory);
        $other = new URLRequest(new URL("https://example.com/other"));
        $cache->storeCachedResponse($this->response("abc"), $this->request);
        $cache->storeCachedResponse($this->response("def"), $other);
        $this->assertSame(6, $cache->currentMemoryUsage);
        $cache->storeCachedResponse($this->response("123456"), $this->request);
        $this->assertSame("123456", $cache->cachedResponse($this->request)?->data);
        $this->assertNull($cache->cachedResponse($other));
        $this->assertSame(6, $cache->currentMemoryUsage);
    }

    public function testRepeatedReplacementDoesNotLeaveEvictionEntries(): void
    {
        $cache = new URLCache(10, 0, $this->directory);
        $cache->storeCachedResponse($this->response("abc"), $this->request);
        $cache->storeCachedResponse($this->response("def"), $this->request);
        $cache->storeCachedResponse($this->response("ghi"), $this->request);
        $other = new URLRequest(new URL("https://example.com/other"));
        $cache->storeCachedResponse($this->response("12345678"), $other);
        $this->assertNull($cache->cachedResponse($this->request));
        $this->assertSame("12345678", $cache->cachedResponse($other)?->data);
        $this->assertSame(8, $cache->currentMemoryUsage);
    }

    public function testDisabledAndUndersizedCachesDoNotStoreResponses(): void
    {
        $disabled = new URLCache(0, 0, $this->directory);
        $disabled->storeCachedResponse($this->response(""), $this->request);
        $this->assertNull($disabled->cachedResponse($this->request));
        $small = new URLCache(2, 2, $this->directory);
        $small->storeCachedResponse($this->response("abc", URLCacheStoragePolicy::allowed), $this->request);
        $this->assertNull($small->cachedResponse($this->request));
        $this->assertSame(0, $small->currentMemoryUsage);
        $this->assertSame(0, $small->currentDiskUsage);
    }

    public function testMemoryOnlyReplacementRemovesTheOldDiskResponse(): void
    {
        $cache = new URLCache(100000, 100000, $this->directory);
        $cache->storeCachedResponse($this->response("old", URLCacheStoragePolicy::allowed), $this->request);
        $cache->storeCachedResponse($this->response("new"), $this->request);
        $this->assertSame("new", $cache->cachedResponse($this->request)?->data);
        $this->assertSame(0, $cache->currentDiskUsage);
        $this->assertNull(new URLCache(0, 100000, $this->directory)->cachedResponse($this->request));
    }

    #[DataProvider("distinctRequests")]
    public function testRequestIdentity(string $url, string $method): void
    {
        $cache = new URLCache(100, 0, $this->directory);
        $cache->storeCachedResponse($this->response("first"), $this->request);
        $other = new URLRequest(new URL($url));
        $other->httpMethod = $method;
        $this->assertNull($cache->cachedResponse($other));
    }

    public static function distinctRequests(): array
    {
        return [["http://example.com/item", "GET"], ["https://example.com/item?page=2", "GET"], ["https://example.com/item", "HEAD"], ["https://example.com:8443/item", "GET"]];
    }

    #[DataProvider("storagePolicies")]
    public function testStoragePolicy(URLCacheStoragePolicy $policy, bool $memory, bool $disk): void
    {
        $cache = new URLCache(100000, 100000, $this->directory);
        $cache->storeCachedResponse($this->response("payload", $policy), $this->request);
        $this->assertSame($memory, $cache->currentMemoryUsage > 0);
        $this->assertSame($disk, $cache->currentDiskUsage > 0);
        $reopened = new URLCache(0, 100000, $this->directory);
        $this->assertSame($disk ? "payload" : null, $reopened->cachedResponse($this->request)?->data);
    }

    public static function storagePolicies(): array
    {
        return [[URLCacheStoragePolicy::allowed, true, true], [URLCacheStoragePolicy::allowedInMemoryOnly, true, false], [URLCacheStoragePolicy::notAllowed, false, false]];
    }

    public function testDiskReplacementAndRemoval(): void
    {
        $cache = new URLCache(0, 100000, $this->directory);
        $cache->storeCachedResponse($this->response("old", URLCacheStoragePolicy::allowed), $this->request);
        $cache->storeCachedResponse($this->response("new", URLCacheStoragePolicy::allowed), $this->request);
        $reopened = new URLCache(0, 100000, $this->directory);
        $this->assertSame("new", $reopened->cachedResponse($this->request)?->data);
        $this->assertGreaterThan(0, $reopened->currentDiskUsage);
        $reopened->removeCachedResponse($this->request);
        $this->assertNull($reopened->cachedResponse($this->request));
        $this->assertSame(0, $reopened->currentDiskUsage);
    }

    public function testDiskCapacityEvictsOldEntries(): void
    {
        $cache = new URLCache(0, 100000, $this->directory);
        $response = $this->response("abc", URLCacheStoragePolicy::allowed);
        $cache->storeCachedResponse($response, $this->request);
        $cost = $cache->currentDiskUsage;
        $this->assertGreaterThan(0, $cost);
        $cache->diskCapacity = $cost;
        $other = new URLRequest(new URL("https://example.com/other"));
        $cache->storeCachedResponse($response, $other);
        $this->assertNull($cache->cachedResponse($this->request));
        $this->assertSame("abc", $cache->cachedResponse($other)?->data);
        $this->assertSame($cost, $cache->currentDiskUsage);
    }

    public function testRemovalByDateAndClearing(): void
    {
        $cache = new URLCache(100000, 100000, $this->directory);
        $cache->storeCachedResponse($this->response("abc", URLCacheStoragePolicy::allowed), $this->request);
        $cache->removeCachedResponses(Date::dateWithTimeIntervalSince1970(4102444800.0));
        $this->assertNotNull($cache->cachedResponse($this->request));
        $cache->removeCachedResponses(Date::dateWithTimeIntervalSince1970(946684800.0));
        $this->assertNull($cache->cachedResponse($this->request));
        $this->assertSame(0, $cache->currentMemoryUsage);
        $this->assertSame(0, $cache->currentDiskUsage);
        $cache->storeCachedResponse($this->response("abc", URLCacheStoragePolicy::allowed), $this->request);
        $cache->removeAllCachedResponses();
        $this->assertNull($cache->cachedResponse($this->request));
        $this->assertSame(0, $cache->currentMemoryUsage);
        $this->assertSame(0, $cache->currentDiskUsage);
    }
}
