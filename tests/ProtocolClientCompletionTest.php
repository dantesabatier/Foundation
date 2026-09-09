<?php

declare(strict_types=1);

namespace Sabatier\Foundation\Tests;

use Closure;
use PHPUnit\Framework\TestCase;
use ReflectionMethod;
use Sabatier\Foundation\ArrayClass;
use Sabatier\Foundation\Error;
use Sabatier\Foundation\Networking\HTTPURLResponse;
use Sabatier\Foundation\Networking\URLAuthenticationChallenge;
use Sabatier\Foundation\Networking\URLRequest;
use Sabatier\Foundation\Networking\URLResponse;
use Sabatier\Foundation\Networking\URLSession;
use Sabatier\Foundation\Networking\URLSessionConfiguration;
use Sabatier\Foundation\Networking\URLSessionDelegate;
use Sabatier\Foundation\Networking\URLSessionDownloadDelegate;
use Sabatier\Foundation\Networking\URLSessionDownloadTask;
use Sabatier\Foundation\Networking\URLSessionTask;
use Sabatier\Foundation\Networking\URLSessionTaskDelegate;
use Sabatier\Foundation\Networking\URLSessionTaskState;
use Sabatier\Foundation\Tests\Fixtures\StubURLProtocol;
use Sabatier\Foundation\URL;

/**
 * A delegate that records the completion callbacks it receives, so a test can assert
 * which ones ProtocolClient drove and in what order.
 */
final class ProtocolClientCompletionDelegate implements URLSessionDelegate, URLSessionTaskDelegate, URLSessionDownloadDelegate
{
    /** @var list<string> $events The callbacks this delegate received, in order. */
    public array $events = [];
    public ?URL $downloadLocation = null;

    public function urlSessionDidBecomeInvalidWithError(URLSession $session, ?Error $error = null): void
    {
    }

    public function urlSessionDidReceiveChallenge(URLSession $session, URLAuthenticationChallenge $challenge, Closure $completionHandler): void
    {
    }

    public function urlSessionTaskDidComplete(URLSession $session, URLSessionTask $task, ?Error $error = null): void
    {
        $this->events[] = "didComplete";
    }

    public function urlSessionTaskWillPerformHTTPRedirection(URLSession $session, URLSessionTask $task, HTTPURLResponse $response, URLRequest $request, Closure $completionHandler): void
    {
    }

    public function urlSessionTaskDidSendBodyData(URLSession $session, URLSessionTask $task, float $bytesSent, float $totalBytesSent, float $totalBytesExpectedToSend): void
    {
    }

    public function urlSessionTaskNeedNewBodyStream(URLSession $session, URLSessionTask $task, Closure $completionHandler): void
    {
    }

    public function urlSessionTaskDidReceiveChallenge(URLSession $session, URLSessionTask $task, URLAuthenticationChallenge $challenge, Closure $completionHandler): void
    {
    }

    public function urlSessionDownloadTaskDidFinishDownloadingToURL(URLSession $session, URLSessionDownloadTask $downloadTask, ?URL $location): void
    {
        $this->events[] = "didFinishDownloading";
        $this->downloadLocation = $location;
    }

    public function urlSessionDownloadTaskDidWriteData(URLSession $session, URLSessionDownloadTask $downloadTask, int $bytesWritten, float $totalBytesWritten, float $totalBytesExpectedToWrite): void
    {
    }
}

/**
 * Tests how src/Networking/ProtocolClient.php finishes a task. completeTask() branches
 * on the session's behaviour for that task — no delegate, a task delegate, a data
 * completion handler or a download completion handler — and each arm has to move the
 * task to completed, notify whoever is listening and unregister it.
 *
 * Regression guards:
 *  - every behaviour ends with the task completed and removed from the registry, so a
 *    finished task never lingers in the session;
 *  - a download hands its temporary file to the receiver and deletes it afterwards: the
 *    file is only valid for the duration of that call, which is what makes the delete
 *    correct rather than premature;
 *  - a download delegate hears didFinishDownloading before didComplete, since the
 *    location is gone by the time the second one runs;
 *  - the completed-state guard makes a second completion a no-op rather than notifying
 *    twice, which is what a protocol reporting completion more than once would cause.
 */
final class ProtocolClientCompletionTest extends TestCase
{
    private function configuration(): URLSessionConfiguration
    {
        $configuration = new URLSessionConfiguration();
        $configuration->protocolClasses = new ArrayClass([StubURLProtocol::class]);
        return $configuration;
    }

    private function protocolOf(URLSessionTask $task): StubURLProtocol
    {
        $protocol = null;
        new ReflectionMethod($task, "getProtocol")->invoke($task, function (mixed $resolved) use (&$protocol): void {
            $protocol = $resolved;
        });
        $this->assertInstanceOf(StubURLProtocol::class, $protocol);
        return $protocol;
    }

    private function request(string $path = "/resource"): URLRequest
    {
        return new URLRequest(new URL("http://stub.example.com" . $path));
    }

    public function testATaskWithNoListenerStillCompletes(): void
    {
        $session = new URLSession($this->configuration());
        $task = $session->dataTaskWithRequest($this->request());
        $task->resume();

        $this->protocolOf($task)->respond(new ArrayClass(["body"]));
        $session->delegateQueue->waitUntilAllOperationsAreFinished();

        $this->assertSame(URLSessionTaskState::completed, $task->state);
    }

    public function testADataCompletionHandlerReceivesTheBody(): void
    {
        $session = new URLSession($this->configuration());
        $received = null;
        $task = $session->dataTaskWithRequest($this->request(), function (?string $data, ?URLResponse $response, ?Error $error) use (&$received): void {
            $received = [$data, $error];
        });
        $task->resume();

        $this->protocolOf($task)->respond(new ArrayClass(["hello", " world"]));
        $session->delegateQueue->waitUntilAllOperationsAreFinished();

        $this->assertSame(["hello world", null], $received, "the chunks arrive concatenated");
        $this->assertSame(URLSessionTaskState::completed, $task->state);
    }

    public function testATaskDelegateIsToldTheTaskCompleted(): void
    {
        $delegate = new ProtocolClientCompletionDelegate();
        $session = new URLSession($this->configuration(), $delegate);
        $task = $session->dataTaskWithRequest($this->request());
        $task->resume();

        $this->protocolOf($task)->respond(new ArrayClass(["body"]));
        $session->delegateQueue->waitUntilAllOperationsAreFinished();

        $this->assertSame(["didComplete"], $delegate->events);
        $this->assertSame(URLSessionTaskState::completed, $task->state);
    }

    public function testADownloadDelegateHearsTheLocationBeforeCompletion(): void
    {
        $delegate = new ProtocolClientCompletionDelegate();
        $session = new URLSession($this->configuration(), $delegate);
        $task = $session->downloadTaskWithRequest($this->request("/file"));
        $task->resume();

        $this->protocolOf($task)->respond(new ArrayClass(["data"]));
        $session->delegateQueue->waitUntilAllOperationsAreFinished();

        $this->assertSame(["didFinishDownloading", "didComplete"], $delegate->events, "the location is gone by the time didComplete runs");
        $this->assertSame(URLSessionTaskState::completed, $task->state);
    }

    public function testADownloadHandsOverItsTemporaryFileAndThenDeletesIt(): void
    {
        $session = new URLSession($this->configuration());
        $delivered = null;
        $task = $session->downloadTaskWithRequest($this->request("/file"), function (?URL $location, ?URLResponse $response, ?Error $error) use (&$delivered): void {
            $delivered = $location;
        });
        $task->resume();

        $temporaryPath = sys_get_temp_dir() . DIRECTORY_SEPARATOR . "sabatier-download-" . getmypid() . ".bin";
        file_put_contents($temporaryPath, "payload");
        StubURLProtocol::setProperty(new URL("file:///" . str_replace("\\", "/", $temporaryPath)), "temporaryFileURL", $task->currentRequest);

        $this->protocolOf($task)->respond(new ArrayClass(["data"]));
        $session->delegateQueue->waitUntilAllOperationsAreFinished();

        $this->assertInstanceOf(URL::class, $delivered, "the handler is given the temporary file");
        $this->assertFileDoesNotExist($temporaryPath, "and the file is removed once the handler has had it");
    }

    public function testCompletingTwiceNotifiesOnlyOnce(): void
    {
        $delegate = new ProtocolClientCompletionDelegate();
        $session = new URLSession($this->configuration(), $delegate);
        $task = $session->dataTaskWithRequest($this->request());
        $task->resume();
        $protocol = $this->protocolOf($task);

        $protocol->respond(new ArrayClass(["body"]));
        $protocol->respond(new ArrayClass(["body again"]));
        $session->delegateQueue->waitUntilAllOperationsAreFinished();

        $this->assertSame(["didComplete"], $delegate->events, "the completed-state guard swallows the second completion");
    }
}
