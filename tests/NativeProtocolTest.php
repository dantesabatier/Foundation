<?php

declare(strict_types=1);

namespace Sabatier\Foundation\Tests;

use PHPUnit\Framework\TestCase;
use ReflectionMethod;
use Sabatier\Foundation\Dictionary;
use Sabatier\Foundation\InternalInconsistencyException;
use Sabatier\Foundation\Networking\CachedURLResponse;
use Sabatier\Foundation\Networking\CompletionActionRawValue;
use Sabatier\Foundation\Networking\EasyHandleProgress;
use Sabatier\Foundation\Networking\HTTPStatusCode;
use Sabatier\Foundation\Networking\HTTPURLProtocol;
use Sabatier\Foundation\Networking\HTTPURLResponse;
use Sabatier\Foundation\Networking\NativeProtocol;
use Sabatier\Foundation\Networking\TransferState;
use Sabatier\Foundation\Networking\URLCacheStoragePolicy;
use Sabatier\Foundation\Networking\URLRequest;
use Sabatier\Foundation\Networking\URLSession;
use Sabatier\Foundation\Networking\URLSessionConfiguration;
use Sabatier\Foundation\URL;

/**
 * Tests the parts of src/Networking/NativeProtocol.php that decide rather than transfer.
 * The protocol is built around a real task but never resumed, so the base-class
 * decisions are reachable without a server: what the subclass is expected to override,
 * what the default answers are, and the invariant the body callback relies on.
 *
 * Regression guards:
 *  - canonicalRequest() is identity at this level; a subclass that needs to rewrite the
 *    request overrides it;
 *  - the base canCache() refuses everything, and HTTPURLProtocol's override is what
 *    actually evaluates freshness — a fresh, unauthenticated 200 is cacheable by that
 *    calculation even though nothing currently asks it to store (see
 *    HTTPURLProtocolServerTest);
 *  - the default completionAction() completes the task rather than redirecting;
 *  - validateHeaderComplete() enforces that body data never arrives before the header
 *    is complete, which the whole append path assumes;
 *  - redirectFor() has no base implementation and demands one from the subclass;
 *  - updateProgressMeter() sums the upload and download halves into the task's progress.
 */
final class NativeProtocolTest extends TestCase
{
    private function protocol(): HTTPURLProtocol
    {
        $session = new URLSession(new URLSessionConfiguration());
        $request = new URLRequest(new URL("http://127.0.0.1:9/resource"));
        $task = $session->dataTaskWithRequest($request, function (): void {
        });
        return new HTTPURLProtocol($task);
    }

    private function invokeBase(string $method, mixed ...$arguments): mixed
    {
        return new ReflectionMethod(NativeProtocol::class, $method)->invoke($this->protocol(), ...$arguments);
    }

    private function freshResponse(): HTTPURLResponse
    {
        return new HTTPURLResponse(new URL("http://127.0.0.1:9/resource"), HTTPStatusCode::ok, "HTTP/1.1", new Dictionary(["Cache-Control" => "max-age=300"]));
    }

    public function testCanonicalRequestIsIdentityAtThisLevel(): void
    {
        $request = new URLRequest(new URL("http://127.0.0.1:9/resource"));

        $this->assertSame($request, NativeProtocol::canonicalRequest($request));
        $this->assertSame($request, HTTPURLProtocol::canonicalRequest($request), "the HTTP subclass does not rewrite it either");
    }

    public function testTheBaseProtocolCachesNothing(): void
    {
        $cacheable = new CachedURLResponse($this->freshResponse(), "body", URLCacheStoragePolicy::allowed);

        $this->assertFalse($this->invokeBase("canCache", $cacheable), "the base class opts out and leaves the decision to subclasses");
    }

    public function testTheHttpOverrideAcceptsAFreshResponse(): void
    {
        $cacheable = new CachedURLResponse($this->freshResponse(), "body", URLCacheStoragePolicy::allowed);

        $this->assertTrue($this->protocol()->canCache($cacheable), "a fresh, unauthenticated 200 passes the freshness calculation");
    }

    public function testTheHttpOverrideRejectsANoStoreResponse(): void
    {
        $response = new HTTPURLResponse(new URL("http://127.0.0.1:9/resource"), HTTPStatusCode::ok, "HTTP/1.1", new Dictionary(["Cache-Control" => "no-store"]));
        $cacheable = new CachedURLResponse($response, "body", URLCacheStoragePolicy::allowed);

        $this->assertFalse($this->protocol()->canCache($cacheable));
    }

    public function testTheHttpOverrideRejectsAnAuthenticatedResponse(): void
    {
        $response = new HTTPURLResponse(new URL("http://127.0.0.1:9/resource"), HTTPStatusCode::ok, "HTTP/1.1", new Dictionary([
            "Cache-Control" => "max-age=300",
            "WWW-Authenticate" => "Basic realm=\"members\"",
        ]));
        $cacheable = new CachedURLResponse($response, "body", URLCacheStoragePolicy::allowed);

        $this->assertFalse($this->protocol()->canCache($cacheable), "a response carrying an authentication challenge is never stored");
    }

    public function testTheHttpOverrideRejectsAVaryingResponse(): void
    {
        $response = new HTTPURLResponse(new URL("http://127.0.0.1:9/resource"), HTTPStatusCode::ok, "HTTP/1.1", new Dictionary([
            "Cache-Control" => "max-age=300",
            "Vary" => "Accept-Encoding",
        ]));
        $cacheable = new CachedURLResponse($response, "body", URLCacheStoragePolicy::allowed);

        $this->assertFalse($this->protocol()->canCache($cacheable), "a varying response cannot be keyed by URL alone");
    }

    public function testTheDefaultCompletionActionCompletesTheTask(): void
    {
        $request = new URLRequest(new URL("http://127.0.0.1:9/resource"));

        $action = $this->invokeBase("completionAction", $request, $this->freshResponse());

        $this->assertSame(CompletionActionRawValue::completeTask, $action->rawValue);
    }

    public function testValidatingACompleteHeaderYieldsNoReplacement(): void
    {
        $transferState = new TransferState(new URL("http://127.0.0.1:9/resource"))
            ->byAppendingHTTP("HTTP/1.1 200 OK\r\n")
            ->byAppendingHTTP("\r\n");

        $this->assertNull($this->invokeBase("validateHeaderComplete", $transferState), "the base class supplies no synthetic response");
    }

    public function testBodyDataBeforeACompleteHeaderIsFatal(): void
    {
        $transferState = new TransferState(new URL("http://127.0.0.1:9/resource"));

        $this->expectException(InternalInconsistencyException::class);
        $this->expectExceptionMessage("header is not complete");

        $this->invokeBase("validateHeaderComplete", $transferState);
    }

    public function testRedirectionDemandsASubclassImplementation(): void
    {
        $this->expectException(InternalInconsistencyException::class);
        $this->expectExceptionMessage("requires a subclass implementation");

        $this->invokeBase("redirectFor", new URLRequest(new URL("http://127.0.0.1:9/elsewhere")));
    }

    public function testProgressSumsTheUploadAndDownloadHalves(): void
    {
        $session = new URLSession(new URLSessionConfiguration());
        $task = $session->dataTaskWithRequest(new URLRequest(new URL("http://127.0.0.1:9/resource")), function (): void {
        });
        $protocol = new HTTPURLProtocol($task);

        $protocol->updateProgressMeter(new EasyHandleProgress(
            totalBytesSent: 10,
            totalBytesExpectedToSend: 100,
            totalBytesReceived: 20,
            totalBytesExpectedToReceive: 200,
        ));

        $this->assertSame(300.0, $task->progress->totalUnitCount, "the expected totals of both directions are added");
        $this->assertSame(30.0, $task->progress->completedUnitCount, "so are the transferred counts");
    }
}
