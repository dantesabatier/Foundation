<?php

declare(strict_types=1);

namespace Sabatier\Foundation\Tests;

use PHPUnit\Framework\TestCase;
use Sabatier\Foundation\Networking\HTTPCookie;
use Sabatier\Foundation\Networking\HTTPCookieStorage;
use Sabatier\Foundation\Networking\HTTPRequestMethod;
use Sabatier\Foundation\Networking\HTTPURLResponse;
use Sabatier\Foundation\Networking\URLRequest;
use Sabatier\Foundation\Networking\URLSession;
use Sabatier\Foundation\Networking\URLSessionConfiguration;
use Sabatier\Foundation\URL;
use const Sabatier\Foundation\URLErrorTimedOut;

/**
 * End-to-end tests for the URLSession networking stack, driven against PHP's
 * built-in web server.
 *
 * Regression guards:
 *  - the CURL header callback receives CURLINFO_CONTENT_LENGTH_DOWNLOAD, which PHP
 *    reports as float (-1.0 while unknown); under strict_types it must be cast before
 *    reaching didReceiveHeaderData(int). The bug made EVERY request fatal on its first
 *    header line, so any completed task below exercises it.
 */
final class URLSessionTest extends TestCase
{
    /** @var resource|null */
    private static $server;
    private static int $port;
    private static string $host;
    private static string $router;
    private static string $unique;
    private URLSession $session;

    public static function setUpBeforeClass(): void
    {
        self::$unique = uniqid("", true);
        self::$port = 8900 + (getmypid() % 100);
        self::$host = "127.0.0.1:" . self::$port;
        self::$router = sys_get_temp_dir() . DIRECTORY_SEPARATOR . "sabatier-urlsession-router-" . getmypid() . ".php";
        file_put_contents(self::$router, <<<'ROUTER'
<?php
$path = parse_url($_SERVER["REQUEST_URI"], PHP_URL_PATH);
switch ($path) {
    case "/redirect":
        header("Location: /hello?redirected=" . ($_GET["r"] ?? ""), true, 302);
        break;
    case "/slow":
        sleep(2);
        echo "late response";
        break;
    case "/truncated":
        header("Content-Length: 100");
        header("Connection: close");
        echo "short";
        break;
    case "/hello":
        header("Content-Type: text/plain");
        echo "hello world";
        break;
    case "/json":
        header("Content-Type: application/json");
        echo json_encode(["ok" => true, "value" => 42]);
        break;
    case "/echo":
        header("Content-Type: text/plain");
        echo strtoupper((string)file_get_contents("php://input"));
        break;
    case "/header":
        header("X-Test-Header: sabatier");
        echo "with header";
        break;
    case "/missing":
        http_response_code(404);
        echo "not found";
        break;
    case "/set-cookie":
        header("Set-Cookie: session=abc123; Path=/");
        echo "cookie set";
        break;
    case "/show-cookies":
        echo $_COOKIE["session"] ?? "none";
        break;
    default:
        http_response_code(500);
        echo "unexpected path $path";
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
            static::markTestSkipped("unable to start the test server");
        }
        self::$server = $server;
        $ready = false;
        for ($i = 0; $i < 50 && !$ready; $i++) {
            $probe = @fsockopen("127.0.0.1", self::$port, $errorCode, $errorMessage, 0.2);
            if ($probe !== false) {
                fclose($probe);
                $ready = true;
                break;
            }
            usleep(100_000);
        }
        if (!$ready) {
            proc_terminate($server);
            proc_close($server);
            self::$server = null;
            @unlink(self::$router);
            static::markTestSkipped("the test server never became reachable on " . self::$host);
        }
    }

    public static function tearDownAfterClass(): void
    {
        if (is_resource(self::$server)) {
            proc_terminate(self::$server);
            proc_close(self::$server);
            self::$server = null;
        }
        @unlink(self::$router);
    }

    protected function setUp(): void
    {
        $configuration = new URLSessionConfiguration();
        $configuration->urlCache = null;
        $this->session = new URLSession($configuration);
    }

    /**
     * Runs a data task to completion and returns [data, response, error].
     *
     * @return array{string|null, \Sabatier\Foundation\Networking\URLResponse|null, \Sabatier\Foundation\Error|null}
     */
    private function awaitDataTask(URLRequest $request): array
    {
        $result = null;
        $task = $this->session->dataTaskWithRequest($request, function (?string $data, $response, $error) use (&$result): void {
            $result = [$data, $response, $error];
        });
        $task->resume();
        if ($result === null) {
            $this->session->delegateQueue->waitUntilAllOperationsAreFinished();
        }
        return $result ?? [null, null, null];
    }

    public function testDataTask(): void
    {
        $host = self::$host;
        $unique = self::$unique;
        [$data, $response, $error] = $this->awaitDataTask(new URLRequest(new URL("http://$host/hello?r=$unique")));
        $this->assertNull($error, "a successful request reports no error");
        $this->assertSame("hello world", $data, "the body arrives complete");
        $this->assertInstanceOf(HTTPURLResponse::class, $response, "the response is an HTTPURLResponse");
        $this->assertSame(200, $response->statusCode, "status code 200");

        [$data, $response, $error] = $this->awaitDataTask(new URLRequest(new URL("http://$host/json?r=$unique")));
        $this->assertTrue($error === null && $data !== null, "json endpoint responds");
        $decoded = $data !== null ? json_decode($data, true) : null;
        $this->assertTrue(is_array($decoded) && $decoded["ok"] === true && $decoded["value"] === 42, "json body decodes");
    }

    public function testResponseHeaders(): void
    {
        [, $response, $error] = $this->awaitDataTask(new URLRequest(new URL("http://" . self::$host . "/header?r=" . self::$unique)));
        $this->assertTrue($error === null && $response instanceof HTTPURLResponse, "header endpoint responds");
        $this->assertTrue($response instanceof HTTPURLResponse && (string)$response->allHeaderFields["X-Test-Header"] === "sabatier", "custom response header is captured");
    }

    public function testStatusCodes(): void
    {
        [$data, $response] = $this->awaitDataTask(new URLRequest(new URL("http://" . self::$host . "/missing?r=" . self::$unique)));
        $this->assertTrue($response instanceof HTTPURLResponse && $response->statusCode === 404, "404 is reported through the response, not as a transport error");
        $this->assertSame("not found", $data, "the 404 body is still delivered");
    }

    public function testRequestBody(): void
    {
        $request = new URLRequest(new URL("http://" . self::$host . "/echo?r=" . self::$unique));
        $request->httpMethod = HTTPRequestMethod::post;
        $request->httpBody = "sabatier foundation";
        [$data, , $error] = $this->awaitDataTask($request);
        $this->assertNull($error, "POST request completes");
        $this->assertSame("SABATIER FOUNDATION", $data, "the request body reaches the server and the echo comes back");
    }

    public function testCookies(): void
    {
        [$data, , $error] = $this->awaitDataTask(new URLRequest(new URL("http://" . self::$host . "/set-cookie?r=" . self::$unique)));
        $this->assertTrue($error === null && $data === "cookie set", "set-cookie endpoint responds");
        $storage = HTTPCookieStorage::shared();
        $stored = $storage->cookies->first(fn(HTTPCookie $cookie): bool => $cookie->name === "session");
        $this->assertInstanceOf(HTTPCookie::class, $stored, "the Set-Cookie header lands in the shared cookie storage");
        $this->assertSame("abc123", $stored->value, "the stored cookie keeps its value");

        // Round-trip: the configuration attaches stored cookies to subsequent requests
        // (httpShouldSetCookies), so the server must see the cookie back.
        [$data, , $error] = $this->awaitDataTask(new URLRequest(new URL("http://" . self::$host . "/show-cookies?r=" . self::$unique)));
        $this->assertTrue($error === null && $data === "abc123", "stored cookies are sent back on subsequent requests");
    }

    public function testDownloadTask(): void
    {
        $downloadResult = null;
        $task = $this->session->downloadTaskWithURL(new URL("http://" . self::$host . "/hello?download=" . self::$unique), function (?URL $location, $response, $error) use (&$downloadResult): void {
            // Read inside the handler: the file is only valid for its duration, as in Foundation.
            $downloadResult = [$location !== null ? file_get_contents($location->fileSystemRepresentation) : null, $response, $error, $location?->fileSystemRepresentation];
        });
        $task->resume();
        if ($downloadResult === null) {
            $this->session->delegateQueue->waitUntilAllOperationsAreFinished();
        }
        [$contents, $response, $error, $temporaryPath] = $downloadResult ?? [null, null, null, null];
        $this->assertNull($error, "download task completes without error");
        $this->assertSame("hello world", $contents, "the downloaded file holds the body");
        $this->assertTrue($response instanceof HTTPURLResponse && $response->statusCode === 200, "download task reports the response");
        $this->assertTrue($temporaryPath !== null && !file_exists($temporaryPath), "the temporary file is removed after the completion handler returns");
    }

    public function testUploadTask(): void
    {
        $uploadSource = sys_get_temp_dir() . DIRECTORY_SEPARATOR . "sabatier-upload-" . getmypid() . ".txt";
        file_put_contents($uploadSource, "upload payload");
        try {
            $uploadResult = null;
            $request = new URLRequest(new URL("http://" . self::$host . "/echo?upload=" . self::$unique));
            $request->httpMethod = HTTPRequestMethod::post;
            $task = $this->session->uploadTaskWithRequest($request, URL::fileURL($uploadSource), function (?string $data, $response, $error) use (&$uploadResult): void {
                $uploadResult = [$data, $response, $error];
            });
            $task->resume();
            if ($uploadResult === null) {
                $this->session->delegateQueue->waitUntilAllOperationsAreFinished();
            }
            [$data, , $error] = $uploadResult ?? [null, null, null];
            $this->assertNull($error, "upload task completes without error");
            $this->assertSame("UPLOAD PAYLOAD", $data, "the uploaded file body reaches the server");
        } finally {
            @unlink($uploadSource);
        }
    }

    public function testTransportErrors(): void
    {
        $unreachable = new URLRequest(new URL("http://127.0.0.1:1/unreachable"));
        $unreachable->timeoutInterval = 3.0;
        [$data, , $error] = $this->awaitDataTask($unreachable);
        $this->assertNotNull($error, "a connection refusal surfaces as an Error");
        $this->assertTrue($data === null || $data === "", "no body on transport failure");
    }

    public function testRelativeRedirectReachesTheDestination(): void
    {
        [$data, $response, $error] = $this->awaitDataTask(new URLRequest(new URL("http://" . self::$host . "/redirect?r=" . self::$unique)));
        $this->assertNull($error);
        $this->assertSame("hello world", $data);
        $this->assertInstanceOf(HTTPURLResponse::class, $response);
        $this->assertSame(200, $response->statusCode);
        $this->assertSame("/hello", $response->url->path);
    }

    public function testTruncatedResponseReportsATransportError(): void
    {
        [$data, , $error] = $this->awaitDataTask(new URLRequest(new URL("http://" . self::$host . "/truncated?r=" . self::$unique)));
        $this->assertNotNull($error);
        $this->assertNull($data);
        [$data, , $error] = $this->awaitDataTask(new URLRequest(new URL("http://" . self::$host . "/hello?after=truncated")));
        $this->assertNull($error);
        $this->assertSame("hello world", $data);
    }

    public function testRequestTimeoutIsRespected(): void
    {
        $request = new URLRequest(new URL("http://" . self::$host . "/slow?r=" . self::$unique));
        $request->timeoutInterval = 1;
        [$data, , $error] = $this->awaitDataTask($request);
        $this->assertNotNull($error);
        $this->assertSame(URLErrorTimedOut, $error->code);
        $this->assertNull($data);
        [$data, , $error] = $this->awaitDataTask(new URLRequest(new URL("http://" . self::$host . "/hello?after=timeout")));
        $this->assertNull($error);
        $this->assertSame("hello world", $data);
    }
}
