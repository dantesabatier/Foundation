<?php

declare(strict_types=1);

namespace Sabatier\Foundation\Tests;

use Closure;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use ReflectionMethod;
use ReflectionProperty;
use Sabatier\Foundation\Dictionary;
use Sabatier\Foundation\Error;
use Sabatier\Foundation\Networking\CachedURLResponse;
use Sabatier\Foundation\Networking\HTTPStatusCode;
use Sabatier\Foundation\Networking\HTTPURLResponse;
use Sabatier\Foundation\Networking\InternalStateRawValue;
use Sabatier\Foundation\Networking\URLAuthenticationChallenge;
use Sabatier\Foundation\Networking\URLCacheStoragePolicy;
use Sabatier\Foundation\Networking\URLRequest;
use Sabatier\Foundation\Networking\URLSession;
use Sabatier\Foundation\Networking\URLSessionConfiguration;
use Sabatier\Foundation\Networking\URLSessionDelegate;
use Sabatier\Foundation\Networking\URLSessionWebSocketOperation;
use Sabatier\Foundation\Networking\URLSessionWebSocketTask;
use Sabatier\Foundation\Networking\URLSessionWebSocketTaskCloseCode;
use Sabatier\Foundation\Networking\URLSessionWebSocketTaskMessageRawValue;
use Sabatier\Foundation\Networking\WebSocketURLProtocol;
use Sabatier\Foundation\URL;

/**
 * A delegate that does nothing, present only so the session reports taskDelegate
 * behaviour — the mode the WebSocket protocol requires before it will route a frame.
 */
final class WebSocketURLProtocolTestDelegate implements URLSessionDelegate
{
    public function urlSessionDidBecomeInvalidWithError(URLSession $session, ?Error $error = null): void
    {
    }

    public function urlSessionDidReceiveChallenge(URLSession $session, URLAuthenticationChallenge $challenge, Closure $completionHandler): void
    {
    }
}

/**
 * Tests src/Networking/WebSocketURLProtocol.php, which was entirely uncovered. The
 * handshake itself needs a real WebSocket server, but the parts that decide — scheme
 * matching, the cache refusals, and the frame dispatch that turns a received operation
 * into a task-level event — are reachable by feeding frames to the protocol directly.
 *
 * Regression guards:
 *  - canInit() claims ws and wss and leaves http/https to HTTPURLProtocol, which it
 *    extends: getting this wrong would route every plain HTTP request through the
 *    WebSocket protocol;
 *  - a WebSocket exchange is never cached, in either direction;
 *  - a text frame arrives as a string message and a binary frame as data, so the
 *    receiver can tell them apart;
 *  - a close frame decodes its big-endian status code and trailing reason, defaults to
 *    a normal closure when the payload is too short to carry one, and reports an
 *    unrecognized code as unsupportedData rather than failing;
 *  - a ping or a continuation frame from the server is a protocol violation and fails
 *    the transfer instead of being ignored.
 */
final class WebSocketURLProtocolTest extends TestCase
{
    /** @return array{URLSessionWebSocketTask, WebSocketURLProtocol} */
    private function pair(): array
    {
        $session = new URLSession(new URLSessionConfiguration(), new WebSocketURLProtocolTestDelegate());
        $task = $session->webSocketTaskWithURL(new URL("ws://127.0.0.1:9/socket"));
        return [$task, new WebSocketURLProtocol($task)];
    }

    private function deliver(WebSocketURLProtocol $protocol, string $data, URLSessionWebSocketOperation $operation): void
    {
        new ReflectionMethod(WebSocketURLProtocol::class, "notifyTaskAboutReceivedData")->invoke($protocol, $data, $operation);
    }

    /** @return iterable<string, array{string, bool}> */
    public static function schemeProvider(): iterable
    {
        yield "ws" => ["ws://example.test/socket", true];
        yield "wss" => ["wss://example.test/socket", true];
        yield "http" => ["http://example.test/resource", false];
        yield "https" => ["https://example.test/resource", false];
        yield "ftp" => ["ftp://example.test/file", false];
    }

    #[DataProvider("schemeProvider")]
    public function testOnlyWebSocketSchemesAreClaimed(string $url, bool $expected): void
    {
        $this->assertSame($expected, WebSocketURLProtocol::canInit(new URLRequest(new URL($url))));
    }

    public function testAWebSocketExchangeIsNeverCached(): void
    {
        [, $protocol] = $this->pair();
        $response = new HTTPURLResponse(new URL("ws://example.test/socket"), HTTPStatusCode::ok, "HTTP/1.1", new Dictionary(["Cache-Control" => "max-age=300"]));
        $cacheable = new CachedURLResponse($response, "body", URLCacheStoragePolicy::allowed);

        $this->assertFalse($protocol->canCache($cacheable), "even a nominally fresh response is not storable");
        $this->assertFalse($protocol->canRespondFromCache($cacheable), "and nothing is ever served from the cache");
    }

    public function testATextFrameArrivesAsAStringMessage(): void
    {
        [$task, $protocol] = $this->pair();

        $this->deliver($protocol, "hello", URLSessionWebSocketOperation::text);

        $received = null;
        $task->receive(function ($message, $error) use (&$received): void {
            $received = $message;
        });

        $this->assertNotNull($received);
        $this->assertSame(URLSessionWebSocketTaskMessageRawValue::string, $received->rawValue);
        $this->assertSame("hello", $received->string);
    }

    public function testABinaryFrameArrivesAsDataMessage(): void
    {
        [$task, $protocol] = $this->pair();

        $this->deliver($protocol, "\x01\x02\x03", URLSessionWebSocketOperation::binary);

        $received = null;
        $task->receive(function ($message, $error) use (&$received): void {
            $received = $message;
        });

        $this->assertNotNull($received);
        $this->assertSame(URLSessionWebSocketTaskMessageRawValue::data, $received->rawValue);
        $this->assertSame("\x01\x02\x03", $received->data);
    }

    public function testACloseFrameDecodesItsCodeAndReason(): void
    {
        [$task, $protocol] = $this->pair();

        // A close payload is a big-endian status code followed by the reason text.
        $this->deliver($protocol, pack("n", URLSessionWebSocketTaskCloseCode::normalClosure->value) . "goodbye", URLSessionWebSocketOperation::close);

        $this->assertSame(URLSessionWebSocketTaskCloseCode::normalClosure, $task->closeCode);
        $this->assertSame("goodbye", $task->closeReason);
    }

    public function testACloseFrameWithoutAPayloadIsANormalClosure(): void
    {
        [$task, $protocol] = $this->pair();

        $this->deliver($protocol, "", URLSessionWebSocketOperation::close);

        $this->assertSame(URLSessionWebSocketTaskCloseCode::normalClosure, $task->closeCode, "too short to carry a code, so the default stands");
        $this->assertSame("", $task->closeReason);
    }

    public function testAnUnrecognizedCloseCodeIsReportedAsUnsupportedData(): void
    {
        [$task, $protocol] = $this->pair();

        $this->deliver($protocol, pack("n", 4999) . "x", URLSessionWebSocketOperation::close);

        $this->assertSame(URLSessionWebSocketTaskCloseCode::unsupportedData, $task->closeCode);
    }

    /** @return iterable<string, array{URLSessionWebSocketOperation}> */
    public static function unexpectedOperationProvider(): iterable
    {
        yield "ping" => [URLSessionWebSocketOperation::ping];
        yield "continuation" => [URLSessionWebSocketOperation::cont];
    }

    #[DataProvider("unexpectedOperationProvider")]
    public function testAFrameTheClientShouldNeverReceiveFailsTheTransfer(URLSessionWebSocketOperation $operation): void
    {
        [, $protocol] = $this->pair();

        // The protocol reports the violation through trigger_error before failing, and
        // the suite runs with failOnNotice, so the notice is expected here.
        @$this->deliver($protocol, "x", $operation);

        $state = new ReflectionProperty(WebSocketURLProtocol::class, "internalState")->getValue($protocol);

        $this->assertSame(InternalStateRawValue::transferFailed, $state->rawValue);
    }
}
