<?php

declare(strict_types=1);

namespace Sabatier\Foundation\Tests;

use Override;
use PHPUnit\Framework\TestCase;
use Sabatier\Foundation\ArrayClass;
use Sabatier\Foundation\Error;
use Sabatier\Foundation\InternalInconsistencyException;
use Sabatier\Foundation\Networking\CachedURLResponse;
use Sabatier\Foundation\Networking\ProtocolClient;
use Sabatier\Foundation\Networking\URLAuthenticationChallenge;
use Sabatier\Foundation\Networking\URLProtectionSpace;
use Sabatier\Foundation\Networking\URLRequest;
use Sabatier\Foundation\Networking\URLResponse;
use Sabatier\Foundation\Networking\URLSession;
use Sabatier\Foundation\Networking\URLSessionConfiguration;
use Sabatier\Foundation\Networking\URLSessionTaskState;
use Sabatier\Foundation\Tests\Fixtures\StubURLProtocol;
use Sabatier\Foundation\URL;

use const Sabatier\Foundation\CocoaErrorDomain;
use const Sabatier\Foundation\UserCancelledError;

/**
 * Tests the callbacks in src/Networking/ProtocolClient.php that no protocol currently
 * drives, so they are unreachable through an ordinary transfer and only a direct call
 * gets to them.
 *
 * Regression guards:
 *  - urlProtocolWasRedirectedToRedirectResponse() is a fatal error rather than a silent
 *    no-op: redirects are followed inside HTTPURLProtocol, and a protocol reporting one
 *    through this callback means it took a route the session cannot complete;
 *  - urlProtocolCachedResponseIsValid() accepts the notification and does nothing,
 *    which is what lets a protocol report cache validation without the session having
 *    to act on it;
 *  - urlProtocolDidCancel() completes the task with the Cocoa user-cancelled error
 *    rather than a transport error, so a dismissed authentication challenge is
 *    reported as a cancellation.
 */
final class ProtocolClientTest extends TestCase
{
    private URLSession $session;

    #[Override]
    protected function setUp(): void
    {
        $configuration = new URLSessionConfiguration();
        $configuration->protocolClasses = new ArrayClass([StubURLProtocol::class]);
        $this->session = new URLSession($configuration);
    }

    /** @return array{StubURLProtocol, ProtocolClient} */
    private function protocol(?Error &$reportedError = null): array
    {
        $task = $this->session->dataTaskWithRequest(new URLRequest(new URL("http://stub.example.com/resource")), function (?string $data, ?URLResponse $response, ?Error $error) use (&$reportedError): void {
            $reportedError = $error;
        });
        $client = new ProtocolClient();
        return [new StubURLProtocol($task, null, $client), $client];
    }

    private function challenge(): URLAuthenticationChallenge
    {
        return new URLAuthenticationChallenge(new URLProtectionSpace("stub.example.com", 80, protocol: "http", realm: "members"));
    }

    public function testReportingARedirectThroughTheClientIsFatal(): void
    {
        [$protocol, $client] = $this->protocol();

        $this->expectException(InternalInconsistencyException::class);
        $this->expectExceptionMessage("doesn't currently handle redirects directly");

        $client->urlProtocolWasRedirectedToRedirectResponse($protocol, new URLRequest(new URL("http://stub.example.com/elsewhere")), new URLResponse(new URL("http://stub.example.com/resource")));
    }

    public function testCacheValidationIsAcceptedWithoutActingOnIt(): void
    {
        [$protocol, $client] = $this->protocol();
        $cachedResponse = new CachedURLResponse(new URLResponse(new URL("http://stub.example.com/resource")), "body");

        $client->urlProtocolCachedResponseIsValid($protocol, $cachedResponse);

        $this->assertSame(URLSessionTaskState::suspended, $protocol->task->state, "the task is left as it was");
    }

    public function testCancellingAChallengeCompletesTheTaskAsCancelled(): void
    {
        $reportedError = null;
        [$protocol, $client] = $this->protocol($reportedError);

        $client->urlProtocolDidCancel($protocol, $this->challenge());
        $this->session->delegateQueue->waitUntilAllOperationsAreFinished();

        $this->assertInstanceOf(Error::class, $reportedError);
        $this->assertSame(CocoaErrorDomain, $reportedError->domain, "a dismissed challenge is a cancellation, not a transport failure");
        $this->assertSame(UserCancelledError, $reportedError->code);
        $this->assertSame(URLSessionTaskState::completed, $protocol->task->state);
    }
}
