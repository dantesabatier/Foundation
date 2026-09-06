<?php

declare(strict_types=1);

namespace Sabatier\Foundation\Tests;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Sabatier\Foundation\ArrayClass;
use Sabatier\Foundation\Error;
use Sabatier\Foundation\Networking\HTTPURLResponse;
use Sabatier\Foundation\Networking\URLProtocol;
use Sabatier\Foundation\Networking\URLSession;
use Sabatier\Foundation\Networking\URLSessionConfiguration;
use Sabatier\Foundation\Networking\URLSessionTask;
use Sabatier\Foundation\Networking\URLSessionTaskState;
use Sabatier\Foundation\Tests\Fixtures\StubURLProtocol;
use Sabatier\Foundation\URL;

require_once __DIR__ . "/Fixtures/StubURLProtocol.php";

/**
 * Regression tests for URLSession protocol callbacks and task transitions without a transport.
 * Each session selects its own stub; no test registers a protocol globally or uses a shared cache.
 */
final class URLSessionProtocolTest extends TestCase
{
    private URLSession $session;

    protected function setUp(): void
    {
        $configuration = new URLSessionConfiguration();
        $configuration->protocolClasses = new ArrayClass([StubURLProtocol::class]);
        $configuration->urlCache = null;
        $configuration->httpCookieStorage = null;
        $configuration->urlCredentialStorage = null;
        $this->session = new URLSession($configuration);
    }

    private function protocol(URLSessionTask $task): StubURLProtocol
    {
        $result = null;
        $task->getProtocol(function (?URLProtocol $protocol) use (&$result): void {
            $result = $protocol;
        });
        $this->assertInstanceOf(StubURLProtocol::class, $result);
        return $result;
    }

    /**
     * @param array<string> $chunks
     * @param string $expected
     */
    #[DataProvider("responseChunks")]
    public function testCompletionReceivesTheWholeBody(array $chunks, string $expected): void
    {
        $calls = 0;
        $task = $this->session->dataTaskWithURL(new URL("https://stub.example.com/data"), function ($data, $response, $error) use (&$calls, $expected): void {
            $calls += 1;
            $this->assertSame($expected, $data);
            $this->assertInstanceOf(HTTPURLResponse::class, $response);
            $this->assertSame(200, $response->statusCode);
            $this->assertNull($error);
        });
        $this->assertSame(URLSessionTaskState::suspended, $task->state);
        $task->resume();
        $this->assertSame(URLSessionTaskState::running, $task->state);
        $this->protocol($task)->respond(new ArrayClass($chunks));
        $this->assertSame(1, $calls);
        $this->assertSame(URLSessionTaskState::completed, $task->state);
        $this->assertSame(0, $this->session->tasks()["dataTasks"]->count);
        $task->resume();
        $task->cancel();
        $this->assertSame(1, $calls);
    }

    public static function responseChunks(): array
    {
        return [[["hello"], "hello"], [["hel", "lo"], "hello"], [["0", ""], "0"], [[], ""]];
    }

    public function testFailureCompletesAndRemovesTheTask(): void
    {
        $failure = new Error("StubError", 1);
        $calls = 0;
        $task = $this->session->dataTaskWithURL(new URL("https://stub.example.com/error"), function ($data, $response, $error) use (&$calls, $failure): void {
            $calls += 1;
            $this->assertNull($data);
            $this->assertNull($response);
            $this->assertSame($failure, $error);
        });
        $task->resume();
        $this->protocol($task)->fail($failure);
        $this->assertSame($failure, $task->error);
        $this->assertSame(URLSessionTaskState::completed, $task->state);
        $this->assertSame(1, $calls);
        $this->assertSame(0, $this->session->tasks()["dataTasks"]->count);
    }

    public function testResumeAndSuspendAreBalanced(): void
    {
        $task = $this->session->dataTaskWithURL(new URL("https://stub.example.com/pending"));
        $protocol = $this->protocol($task);
        $task->resume();
        $task->resume();
        $this->assertSame(1, $protocol->startCount);
        $task->suspend();
        $task->suspend();
        $this->assertSame(1, $protocol->stopCount);
        $task->resume();
        $this->assertSame(URLSessionTaskState::suspended, $task->state);
        $task->resume();
        $this->assertSame(URLSessionTaskState::running, $task->state);
        $this->assertSame(2, $protocol->startCount);
        $task->cancel();
        $this->assertSame(URLSessionTaskState::completed, $task->state);
    }

    #[DataProvider("cancellationStates")]
    public function testCancellationCompletesOnce(bool $resume, string $url): void
    {
        $calls = 0;
        $task = $this->session->dataTaskWithURL(new URL($url), function ($data, $response, $error) use (&$calls): void {
            $calls += 1;
            $this->assertNull($data);
            $this->assertNull($response);
            $this->assertInstanceOf(Error::class, $error);
        });
        if ($resume) {
            $task->resume();
        }
        $task->cancel();
        $task->cancel();
        $this->assertSame(URLSessionTaskState::completed, $task->state);
        $this->assertSame(1, $calls);
        $this->assertSame(0, $this->session->tasks()["dataTasks"]->count);
    }

    public static function cancellationStates(): array
    {
        return [[false, "https://stub.example.com/pending"], [true, "https://stub.example.com/pending"], [false, "https://unsupported.example.com/pending"]];
    }
}
