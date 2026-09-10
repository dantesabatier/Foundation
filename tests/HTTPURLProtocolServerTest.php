<?php

declare(strict_types=1);

namespace Sabatier\Foundation\Tests;

use Override;
use PHPUnit\Framework\TestCase;
use Sabatier\Foundation\Networking\HTTPStatusCode;
use Sabatier\Foundation\Networking\HTTPURLResponse;
use Sabatier\Foundation\Networking\URLCache;
use Sabatier\Foundation\Networking\URLRequest;
use Sabatier\Foundation\Networking\URLRequestCachePolicy;
use Sabatier\Foundation\Networking\URLResponse;
use Sabatier\Foundation\Networking\URLSession;
use Sabatier\Foundation\Networking\URLSessionConfiguration;
use Sabatier\Foundation\URL;
use Throwable;

/**
 * Drives HTTPURLProtocol against a real PHP development server, which is the only way to
 * reach the redirect chain, the upload path and the cache decision: those live behind a
 * live CURL transfer and no amount of unit-level construction gets to them.
 *
 * The server runs on a port derived from the process id, the way URLSessionTest already
 * does it, so parallel runs of the two suites never collide.
 *
 * Regression guards:
 *  - a redirect chain is followed to the end and the response reports the final URL,
 *    not the one originally requested;
 *  - a request body reaches the server intact, and the protocol reports the response
 *    that comes back;
 *  - a 404 arrives as a response with its status code, never as a transport error;
 *  - HTTP responses are not written to the URLCache. HTTPURLProtocol implements the
 *    full freshness calculation in canCache(), but nothing ever calls the storage path
 *    with an allowed policy — every emitter in src/Networking passes notAllowed except
 *    DataURLProtocol — so the cache stays empty however cacheable the response is.
 *    Pinned here so wiring the policy through becomes a deliberate change.
 */
final class HTTPURLProtocolServerTest extends TestCase
{
    /** @var resource|null */
    private static $server;
    private static int $port;
    private static string $host;
    private static string $router;

    #[Override]
    public static function setUpBeforeClass(): void
    {
        // A different range from URLSessionTest's 8900-8999, so the two suites can run at once.
        self::$port = 8600 + (getmypid() % 100);
        self::$host = "127.0.0.1:" . self::$port;
        self::$router = sys_get_temp_dir() . DIRECTORY_SEPARATOR . "sabatier-httpprotocol-router-" . getmypid() . ".php";
        file_put_contents(self::$router, <<<'ROUTER'
<?php
$path = parse_url($_SERVER["REQUEST_URI"], PHP_URL_PATH);
switch ($path) {
    case "/cacheable":
        header("Content-Type: text/plain");
        header("Cache-Control: max-age=300");
        header("ETag: \"v1\"");
        echo "cacheable body";
        break;
    case "/no-store":
        header("Cache-Control: no-store");
        echo "never cached";
        break;
    case "/hop1":
        header("Location: /hop2", true, 302);
        break;
    case "/hop2":
        header("Location: /arrived", true, 302);
        break;
    case "/arrived":
        header("Content-Type: text/plain");
        echo "arrived";
        break;
    case "/upload":
        header("Content-Type: text/plain");
        echo "received:" . strlen((string)file_get_contents("php://input"));
        break;
    case "/method":
        header("Content-Type: text/plain");
        echo $_SERVER["REQUEST_METHOD"];
        break;
    default:
        http_response_code(404);
        echo "not found";
}
ROUTER);

        $server = proc_open(
            [PHP_BINARY, "-S", self::$host, self::$router],
            [1 => ["pipe", "w"], 2 => ["pipe", "w"]],
            $pipes,
            null,
            null,
            ["bypass_shell" => true]
        );
        if (!is_resource($server)) {
            @unlink(self::$router);
            self::markTestSkipped("unable to start the test server");
        }
        self::$server = $server;
        for ($attempt = 0; $attempt < 50; $attempt++) {
            $probe = @fsockopen("127.0.0.1", self::$port, $errorCode, $errorMessage, 0.2);
            if ($probe !== false) {
                fclose($probe);
                return;
            }
            usleep(100_000);
        }
        proc_terminate($server);
        proc_close($server);
        self::$server = null;
        @unlink(self::$router);
        self::markTestSkipped("the test server never became reachable on " . self::$host);
    }

    #[Override]
    public static function tearDownAfterClass(): void
    {
        if (is_resource(self::$server)) {
            proc_terminate(self::$server);
            proc_close(self::$server);
            self::$server = null;
        }
        @unlink(self::$router);
    }

    private function session(?URLCache $cache = null): URLSession
    {
        $configuration = new URLSessionConfiguration();
        $configuration->urlCache = $cache;
        return new URLSession($configuration);
    }

    /**
     * @return array{string|null, URLResponse|null, mixed}
     * @throws Throwable
     */
    private function await(URLSession $session, URLRequest $request): array
    {
        $result = null;
        $task = $session->dataTaskWithRequest($request, function (?string $data, $response, $error) use (&$result): void {
            $result = [$data, $response, $error];
        });
        $task->resume();
        if ($result === null) {
            $session->delegateQueue->waitUntilAllOperationsAreFinished();
        }
        return $result ?? [null, null, null];
    }

    private function url(string $path): URL
    {
        return new URL("http://" . self::$host . $path);
    }

    /** @throws Throwable */
    public function testARedirectChainIsFollowedToItsDestination(): void
    {
        [$data, $response, $error] = $this->await($this->session(), new URLRequest($this->url("/hop1")));

        $this->assertNull($error);
        $this->assertSame("arrived", $data, "the body is the one the last hop served");
        $this->assertInstanceOf(HTTPURLResponse::class, $response);
        $this->assertSame(HTTPStatusCode::ok, $response->statusCode, "the redirects themselves are not reported");
        $this->assertStringEndsWith("/arrived", (string)$response->url, "the response names the final URL");
    }

    /** @throws Throwable */
    public function testARequestBodyReachesTheServer(): void
    {
        $request = new URLRequest($this->url("/upload"));
        $request->httpMethod = "POST";
        $request->httpBody = str_repeat("x", 500);

        [$data, $response, $error] = $this->await($this->session(), $request);

        $this->assertNull($error);
        $this->assertSame("received:500", $data, "the whole body arrived");
        $this->assertInstanceOf(HTTPURLResponse::class, $response);
    }

    /** @throws Throwable */
    public function testTheRequestMethodIsSentAsGiven(): void
    {
        $request = new URLRequest($this->url("/method"));
        $request->httpMethod = "DELETE";

        [$data, , $error] = $this->await($this->session(), $request);

        $this->assertNull($error);
        $this->assertSame("DELETE", $data);
    }

    /** @throws Throwable */
    public function testANotFoundIsAResponseAndNotATransportError(): void
    {
        [, $response, $error] = $this->await($this->session(), new URLRequest($this->url("/missing")));

        $this->assertNull($error, "the transport succeeded; the server merely said no");
        $this->assertInstanceOf(HTTPURLResponse::class, $response);
        $this->assertSame(HTTPStatusCode::notFound, $response->statusCode);
    }

    /** @throws Throwable */
    public function testACacheableResponseIsStillNotWrittenToTheCache(): void
    {
        $cache = new URLCache(4 * 1024 * 1024, 8 * 1024 * 1024);
        $session = $this->session($cache);
        $request = new URLRequest($this->url("/cacheable"));

        [$data, , $error] = $this->await($session, $request);

        $this->assertNull($error);
        $this->assertSame("cacheable body", $data);

        // canCache() would accept this response — 200, max-age=300, no Vary, no auth headers — but the storage path is never reached with an allowed policy, so nothing is written. See the class docblock.
        $this->assertNull($cache->cachedResponse($request), "no entry is stored");
        $this->assertSame(0, $cache->currentMemoryUsage);
    }

    /** @throws Throwable */
    public function testAskingForCachedDataFallsBackToTheNetwork(): void
    {
        $cache = new URLCache(4 * 1024 * 1024, 8 * 1024 * 1024);
        $session = $this->session($cache);
        $request = new URLRequest($this->url("/cacheable"));
        $request->cachePolicy = URLRequestCachePolicy::returnCacheDataElseLoad;

        [$data, $response, $error] = $this->await($session, $request);

        $this->assertNull($error);
        $this->assertSame("cacheable body", $data);
        $this->assertInstanceOf(HTTPURLResponse::class, $response);
    }

    /** @throws Throwable */
    public function testANoStoreResponseIsNotCachedEither(): void
    {
        $cache = new URLCache(4 * 1024 * 1024, 8 * 1024 * 1024);
        $session = $this->session($cache);
        $request = new URLRequest($this->url("/no-store"));

        [$data, , $error] = $this->await($session, $request);

        $this->assertNull($error);
        $this->assertSame("never cached", $data);
        $this->assertNull($cache->cachedResponse($request));
    }
}
