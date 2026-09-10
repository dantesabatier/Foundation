<?php

declare(strict_types=1);

namespace Sabatier\Foundation\Tests;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Sabatier\Foundation\ArrayClass;
use Override;
use Sabatier\Foundation\Error;
use Sabatier\Foundation\Networking\HTTPURLResponse;
use Sabatier\Foundation\Networking\URLProtocol;
use Sabatier\Foundation\Networking\URLSession;
use Sabatier\Foundation\Networking\URLSessionConfiguration;
use Sabatier\Foundation\Networking\URLSessionDataDelegate;
use Sabatier\Foundation\Networking\URLSessionDelegate;
use Sabatier\Foundation\Networking\URLSessionResponseDisposition;
use Sabatier\Foundation\Networking\URLSessionTask;
use Sabatier\Foundation\Networking\URLSessionTaskState;
use Sabatier\Foundation\Tests\Fixtures\StubURLProtocol;
use Sabatier\Foundation\URL;
use Throwable;

require_once __DIR__ . "/Fixtures/StubURLProtocol.php";

/**
 * Regression tests for URLSession protocol callbacks and task transitions without a transport.
 * Each session selects its own stub; no test registers a protocol globally or uses a shared cache.
 */
final class URLSessionProtocolTest extends TestCase
{
    private URLSession $session;

    #[Override]
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
     * @throws Throwable
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

    /** @throws Throwable */
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

    /** @throws Throwable */
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

    #[DataProvider("delegateCancellation")]
    public function testDelegateCallbackOrder(?string $cancelAt): void
    {
        $events = new ArrayClass();
        $delegate = $this->createMock(URLSessionDataDelegate::class);
        $delegate->expects($this->once())->method("urlSessionDataTaskDidReceiveResponse")->willReturnCallback(function ($session, $task, $response, $completion) use ($events, $cancelAt): void {
            $events->append("response");
            $completion($cancelAt === "response" ? URLSessionResponseDisposition::cancel : URLSessionResponseDisposition::allow);
        });
        $delegate->expects($this->exactly(match ($cancelAt) { "response" => 0, "data" => 1, default => 2 }))->method("urlSessionDataTaskReceiveData")->willReturnCallback(function ($session, $task, $data) use ($events, $cancelAt): void {
            $events->append($data);
            if ($cancelAt === "data") {
                $task->cancel();
            }
        });
        $delegate->expects($this->once())->method("urlSessionTaskDidComplete")->willReturnCallback(function ($session, $task, $error) use ($events, $cancelAt): void {
            $events->append("complete");
            $this->assertSame(URLSessionTaskState::completed, $task->state);
            $this->assertSame($cancelAt !== null, $error instanceof Error);
        });
        $session = new URLSession($this->session->configuration, $delegate);
        $task = $session->dataTaskWithURL(new URL("https://stub.example.com/data"));
        $task->resume();
        $this->protocol($task)->respond(new ArrayClass(["first", "second"]));
        $this->assertSame(match ($cancelAt) {
            "response" => "response,complete",
            "data" => "response,first,complete",
            default => "response,first,second,complete",
        }, $events->join(","));
        $this->assertTrue($session->taskRegistry->isEmpty);
    }

    public static function delegateCancellation(): array
    {
        return [[null], ["response"], ["data"]];
    }

    /** @throws Throwable */
    public function testFinishTasksWaitsForCompletionAndNotifiesOnce(): void
    {
        $events = new ArrayClass();
        $delegate = $this->createMock(URLSessionDelegate::class);
        $delegate->expects($this->once())->method("urlSessionDidBecomeInvalidWithError")->willReturnCallback(function () use ($events): void {
            $events->append("invalid");
        });
        $session = new URLSession($this->session->configuration, $delegate);
        $task = $session->dataTaskWithURL(new URL("https://stub.example.com/data"), function () use ($events): void {
            $events->append("complete");
        });
        $task->resume();
        $session->finishTasksAndInvalidate();
        $session->delegateQueue->waitUntilAllOperationsAreFinished();
        $this->assertTrue($events->isEmpty);
        $this->protocol($task)->respond(new ArrayClass(["data"]));
        $session->delegateQueue->waitUntilAllOperationsAreFinished();
        $session->finishTasksAndInvalidate();
        $session->delegateQueue->waitUntilAllOperationsAreFinished();
        $this->assertSame("complete,invalid", $events->join(","));
    }

    /** @throws Throwable */
    public function testInvalidationCancelsEveryOutstandingTask(): void
    {
        $delegate = $this->createMock(URLSessionDelegate::class);
        $delegate->expects($this->once())->method("urlSessionDidBecomeInvalidWithError");
        $session = new URLSession($this->session->configuration, $delegate);
        $calls = 0;
        $tasks = new ArrayClass();
        for ($i = 0; $i < 3; $i++) {
            $tasks->append($session->dataTaskWithURL(new URL("https://stub.example.com/$i"), function ($data, $response, $error) use (&$calls): void {
                $this->assertInstanceOf(Error::class, $error);
                $calls += 1;
            }));
        }
        $tasks[0]->resume();
        $session->invalidateAndCancel();
        $session->invalidateAndCancel();
        $session->delegateQueue->waitUntilAllOperationsAreFinished();
        $this->assertSame(3, $calls);
        $this->assertTrue($session->taskRegistry->isEmpty);
        $tasks->forEach(fn(URLSessionTask $task) => $this->assertSame(URLSessionTaskState::completed, $task->state));
    }

    #[DataProvider("downloadOutcomes")]
    public function testDownloadCompletionRemovesTheTask(bool $fail): void
    {
        $calls = 0;
        $task = $this->session->downloadTaskWithURL(new URL("https://stub.example.com/download"), function ($location, $response, $error) use (&$calls, $fail): void {
            $calls += 1;
            $this->assertSame($fail, $error instanceof Error);
        });
        $task->resume();
        $protocol = $this->protocol($task);
        if ($fail) {
            $protocol->fail(new Error("StubError", 1));
        } else {
            $protocol->respond(new ArrayClass());
        }
        $this->assertSame(1, $calls);
        $this->assertSame(URLSessionTaskState::completed, $task->state);
        $this->assertTrue($this->session->taskRegistry->isEmpty);
    }

    public static function downloadOutcomes(): array
    {
        return [[false], [true]];
    }

    /** @throws Throwable */
    public function testCancellationAfterGracefulInvalidationNotifiesOnce(): void
    {
        $delegate = $this->createMock(URLSessionDelegate::class);
        $delegate->expects($this->once())->method("urlSessionDidBecomeInvalidWithError");
        $session = new URLSession($this->session->configuration, $delegate);
        $task = $session->dataTaskWithURL(new URL("https://stub.example.com/pending"));
        $session->finishTasksAndInvalidate();
        $session->invalidateAndCancel();
        $session->delegateQueue->waitUntilAllOperationsAreFinished();
        $this->assertSame(URLSessionTaskState::completed, $task->state);
        $this->assertTrue($session->taskRegistry->isEmpty);
    }
}
