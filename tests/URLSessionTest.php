<?php

declare(strict_types=1);

/**
 * Standalone end-to-end tests for the URLSession networking stack, driven against
 * PHP's built-in web server.
 *
 * Run with: php tests/URLSessionTest.php
 * Exits with a non-zero status code if any check fails.
 *
 * Regression guards:
 *  - the CURL header callback receives CURLINFO_CONTENT_LENGTH_DOWNLOAD, which PHP
 *    reports as float (-1.0 while unknown); under strict_types it must be cast before
 *    reaching didReceiveHeaderData(int). The bug made EVERY request fatal on its first
 *    header line, so any completed task below exercises it.
 */

namespace Sabatier\Foundation\Tests;

use Sabatier\Foundation\Networking\HTTPCookie;
use Sabatier\Foundation\Networking\HTTPCookieStorage;
use Sabatier\Foundation\Networking\HTTPRequestMethod;
use Sabatier\Foundation\Networking\HTTPURLResponse;
use Sabatier\Foundation\Networking\URLRequest;
use Sabatier\Foundation\Networking\URLSession;
use Sabatier\Foundation\URL;

require __DIR__ . "/../vendor/autoload.php";

final class URLSessionTestRunner
{
    public static int $passed = 0;
    /** @var list<string> */
    public static array $failures = [];
    private static string $section = "";

    public static function section(string $name): void
    {
        self::$section = $name;
    }

    public static function check(bool $condition, string $message): void
    {
        if ($condition) {
            self::$passed++;
            return;
        }
        $failure = self::$section === "" ? $message : self::$section . ": " . $message;
        self::$failures[] = $failure;
        fwrite(STDERR, "FAIL $failure" . PHP_EOL);
    }

    public static function finish(): never
    {
        $failed = count(self::$failures);
        printf("%d passed, %d failed%s", self::$passed, $failed, PHP_EOL);
        exit($failed > 0 ? 1 : 0);
    }
}

/**
 * Runs a data task to completion and returns [data, response, error].
 *
 * @return array{string|null, \Sabatier\Foundation\Networking\URLResponse|null, \Sabatier\Foundation\Error|null}
 */
function await_data_task(URLSession $session, URLRequest $request): array
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

// ---------------------------------------------------------------------------
// Test server bootstrap
// ---------------------------------------------------------------------------

$port = 8900 + (getmypid() % 100);
$host = "127.0.0.1:$port";
$router = sys_get_temp_dir() . DIRECTORY_SEPARATOR . "sabatier-urlsession-router-" . getmypid() . ".php";
file_put_contents($router, <<<'ROUTER'
<?php
$path = parse_url($_SERVER["REQUEST_URI"], PHP_URL_PATH);
switch ($path) {
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
    default:
        http_response_code(500);
        echo "unexpected path $path";
}
ROUTER);

$server = proc_open(
    [PHP_BINARY, "-S", $host, $router],
    [1 => ["pipe", "w"], 2 => ["pipe", "w"]],
    $pipes,
    null,
    null,
    ["bypass_shell" => true]
);
if (!is_resource($server)) {
    fwrite(STDERR, "unable to start the test server" . PHP_EOL);
    exit(2);
}
$ready = false;
for ($i = 0; $i < 50 && !$ready; $i++) {
    $probe = @fsockopen("127.0.0.1", $port, $errorCode, $errorMessage, 0.2);
    if ($probe !== false) {
        fclose($probe);
        $ready = true;
        break;
    }
    usleep(100_000);
}
if (!$ready) {
    proc_terminate($server);
    fwrite(STDERR, "the test server never became reachable on $host" . PHP_EOL);
    exit(2);
}

$check = URLSessionTestRunner::check(...);
$section = URLSessionTestRunner::section(...);
$session = URLSession::shared();
$unique = uniqid("", true);

try {
    // -----------------------------------------------------------------------
    $section("data task");
    // -----------------------------------------------------------------------

    [$data, $response, $error] = await_data_task($session, new URLRequest(new URL("http://$host/hello?r=$unique")));
    $check($error === null, "a successful request reports no error");
    $check($data === "hello world", "the body arrives complete");
    $check($response instanceof HTTPURLResponse, "the response is an HTTPURLResponse");
    $check($response instanceof HTTPURLResponse && $response->statusCode === 200, "status code 200");

    [$data, $response, $error] = await_data_task($session, new URLRequest(new URL("http://$host/json?r=$unique")));
    $check($error === null && $data !== null, "json endpoint responds");
    $decoded = $data !== null ? json_decode($data, true) : null;
    $check(is_array($decoded) && $decoded["ok"] === true && $decoded["value"] === 42, "json body decodes");

    // -----------------------------------------------------------------------
    $section("response headers");
    // -----------------------------------------------------------------------

    [, $response, $error] = await_data_task($session, new URLRequest(new URL("http://$host/header?r=$unique")));
    $check($error === null && $response instanceof HTTPURLResponse, "header endpoint responds");
    $check($response instanceof HTTPURLResponse && (string)$response->allHeaderFields["X-Test-Header"] === "sabatier", "custom response header is captured");

    // -----------------------------------------------------------------------
    $section("status codes");
    // -----------------------------------------------------------------------

    [$data, $response, $error] = await_data_task($session, new URLRequest(new URL("http://$host/missing?r=$unique")));
    $check($response instanceof HTTPURLResponse && $response->statusCode === 404, "404 is reported through the response, not as a transport error");
    $check($data === "not found", "the 404 body is still delivered");

    // -----------------------------------------------------------------------
    $section("request body");
    // -----------------------------------------------------------------------

    $request = new URLRequest(new URL("http://$host/echo?r=$unique"));
    $request->httpMethod = HTTPRequestMethod::post;
    $request->httpBody = "sabatier foundation";
    [$data, , $error] = await_data_task($session, $request);
    $check($error === null, "POST request completes");
    $check($data === "SABATIER FOUNDATION", "the request body reaches the server and the echo comes back");

    // -----------------------------------------------------------------------
    $section("cookies");
    // -----------------------------------------------------------------------

    [$data, , $error] = await_data_task($session, new URLRequest(new URL("http://$host/set-cookie?r=$unique")));
    $check($error === null && $data === "cookie set", "set-cookie endpoint responds");
    $storage = HTTPCookieStorage::shared();
    $stored = $storage->cookies->first(fn(HTTPCookie $cookie): bool => $cookie->name === "session");
    $check($stored instanceof HTTPCookie, "the Set-Cookie header lands in the shared cookie storage");
    $check($stored instanceof HTTPCookie && $stored->value === "abc123", "the stored cookie keeps its value");

    // -----------------------------------------------------------------------
    $section("download task");
    // -----------------------------------------------------------------------

    $downloadResult = null;
    $task = $session->downloadTaskWithURL(new URL("http://$host/hello?download=$unique"), function (?URL $location, $response, $error) use (&$downloadResult): void {
        // Read inside the handler: the file may be temporary and cleaned up afterwards.
        $downloadResult = [$location !== null ? file_get_contents($location->fileSystemRepresentation) : null, $response, $error];
    });
    $task->resume();
    if ($downloadResult === null) {
        $session->delegateQueue->waitUntilAllOperationsAreFinished();
    }
    [$contents, $response, $error] = $downloadResult ?? [null, null, null];
    $check($error === null, "download task completes without error");
    $check($contents === "hello world", "the downloaded file holds the body");
    $check($response instanceof HTTPURLResponse && $response->statusCode === 200, "download task reports the response");

    // -----------------------------------------------------------------------
    $section("upload task");
    // -----------------------------------------------------------------------

    $uploadSource = sys_get_temp_dir() . DIRECTORY_SEPARATOR . "sabatier-upload-" . getmypid() . ".txt";
    file_put_contents($uploadSource, "upload payload");
    try {
        $uploadResult = null;
        $request = new URLRequest(new URL("http://$host/echo?upload=$unique"));
        $request->httpMethod = HTTPRequestMethod::post;
        $task = $session->uploadTaskWithRequest($request, URL::fileURL($uploadSource), function (?string $data, $response, $error) use (&$uploadResult): void {
            $uploadResult = [$data, $response, $error];
        });
        $task->resume();
        if ($uploadResult === null) {
            $session->delegateQueue->waitUntilAllOperationsAreFinished();
        }
        [$data, , $error] = $uploadResult ?? [null, null, null];
        $check($error === null, "upload task completes without error");
        $check($data === "UPLOAD PAYLOAD", "the uploaded file body reaches the server");
    } finally {
        @unlink($uploadSource);
    }

    // -----------------------------------------------------------------------
    $section("transport errors");
    // -----------------------------------------------------------------------

    $unreachable = new URLRequest(new URL("http://127.0.0.1:1/unreachable"));
    $unreachable->timeoutInterval = 3.0;
    [$data, , $error] = await_data_task($session, $unreachable);
    $check($error !== null, "a connection refusal surfaces as an Error");
    $check($data === null || $data === "", "no body on transport failure");
} finally {
    proc_terminate($server);
    proc_close($server);
    @unlink($router);
}

URLSessionTestRunner::finish();
