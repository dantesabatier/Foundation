<?php

declare(strict_types=1);

namespace Sabatier\Foundation\Tests;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Sabatier\Foundation\ArrayClass;
use Sabatier\Foundation\Error;
use Sabatier\Foundation\Networking\URLSession;
use Sabatier\Foundation\Networking\URLSessionConfiguration;
use Sabatier\Foundation\Networking\URLSessionTaskState;
use Sabatier\Foundation\Networking\URLSessionWebSocketTask;
use Sabatier\Foundation\Networking\URLSessionWebSocketTaskMessage;
use Sabatier\Foundation\Tests\Fixtures\StubURLProtocol;
use Sabatier\Foundation\URL;

/**
 * Failed receive and ping handlers must leave their queues so that later messages cannot complete them twice.
 * Buffered messages must be delivered before reading the transport, and cancellation must release the task from its session.
 */
final class URLSessionWebSocketTaskTest extends TestCase
{
    private URLSession $session;
    private URLSessionWebSocketTask $task;

    protected function setUp(): void
    {
        $configuration = new URLSessionConfiguration();
        $configuration->protocolClasses = new ArrayClass([StubURLProtocol::class]);
        $this->session = new URLSession($configuration);
        $this->task = $this->session->webSocketTaskWithURL(new URL("ws://stub.example.com/socket"));
    }

    public function testFailedReceiveDoesNotDeliverAgainAfterAnotherMessage(): void
    {
        $calls = 0;
        $this->task->receive(function ($message, $error) use (&$calls): void {
            $calls += 1;
            $this->assertNull($message);
            $this->assertInstanceOf(Error::class, $error);
        });
        $this->task->appendReceivedMessage(URLSessionWebSocketTaskMessage::string("late"));
        $this->assertSame(1, $calls);
    }

    public function testFailedPingDoesNotDeliverAgainAfterPong(): void
    {
        $calls = 0;
        $this->task->sendPing(function ($error) use (&$calls): void {
            $calls += 1;
            $this->assertInstanceOf(Error::class, $error);
        });
        $this->task->noteReceivedPong();
        $this->assertSame(1, $calls);
    }

    #[DataProvider("messages")]
    public function testBufferedMessageIsDeliveredBeforeReadingTheTransport(bool $binary, string $payload): void
    {
        $expected = $binary ? URLSessionWebSocketTaskMessage::data($payload) : URLSessionWebSocketTaskMessage::string($payload);
        $this->task->appendReceivedMessage($expected);
        $calls = 0;
        $this->task->receive(function ($message, $error) use (&$calls, $expected): void {
            $calls += 1;
            $this->assertSame($expected, $message);
            $this->assertNull($error);
        });
        $this->assertSame(1, $calls);
    }

    public static function messages(): array
    {
        return [[false, "0"], [true, "\x00\xff"]];
    }

    public function testCancelCompletesQueuedSendsAndRemovesTheTaskOnce(): void
    {
        $calls = 0;
        $this->task->send(URLSessionWebSocketTaskMessage::string("pending"), function ($error) use (&$calls): void {
            $calls += 1;
            $this->assertInstanceOf(Error::class, $error);
        });
        $this->assertSame(0, $calls);
        $this->task->cancel();
        $this->task->cancel();
        $this->assertSame(1, $calls);
        $this->assertSame(URLSessionTaskState::completed, $this->task->state);
        $this->assertTrue($this->session->taskRegistry->isEmpty);
    }

    public function testOperationsAfterCancellationFailOnce(): void
    {
        $this->task->cancel();
        $receives = 0;
        $pings = 0;
        $this->task->receive(function ($message, $error) use (&$receives): void {
            $receives += 1;
            $this->assertNull($message);
            $this->assertInstanceOf(Error::class, $error);
        });
        $this->task->sendPing(function ($error) use (&$pings): void {
            $pings += 1;
            $this->assertInstanceOf(Error::class, $error);
        });
        $this->task->noteReceivedPong();
        $this->task->appendReceivedMessage(URLSessionWebSocketTaskMessage::string("late"));
        $this->assertSame(1, $receives);
        $this->assertSame(1, $pings);
    }
}
