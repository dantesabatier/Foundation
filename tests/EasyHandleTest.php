<?php

declare(strict_types=1);

namespace Sabatier\Foundation\Tests;

use CurlHandle;
use Override;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use ReflectionProperty;
use Sabatier\Foundation\Dictionary;
use Sabatier\Foundation\Error;
use Sabatier\Foundation\Networking\EasyHandle;
use Sabatier\Foundation\Networking\EasyHandleAction;
use Sabatier\Foundation\Networking\EasyHandleDelegate;
use Sabatier\Foundation\Networking\EasyHandlePauseState;
use Sabatier\Foundation\Networking\EasyHandleProgress;
use Sabatier\Foundation\Networking\EasyHandleWriteBufferResult;
use Sabatier\Foundation\Networking\URLSessionConfiguration;
use Sabatier\Foundation\Networking\URLSessionWebSocketOperation;
use Sabatier\Foundation\URL;

use const Sabatier\Foundation\URLErrorBadURL;
use const Sabatier\Foundation\URLErrorBadServerResponse;
use const Sabatier\Foundation\URLErrorCannotFindHost;
use const Sabatier\Foundation\URLErrorFileDoesNotExist;
use const Sabatier\Foundation\URLErrorNetworkConnectionLost;
use const Sabatier\Foundation\URLErrorNoPermissionsToReadFile;
use const Sabatier\Foundation\URLErrorSecureConnectionFailed;
use const Sabatier\Foundation\URLErrorTimedOut;
use const Sabatier\Foundation\URLErrorUnsupportedURL;

/**
 * A delegate that records nothing and proceeds with everything, so an EasyHandle can be
 * built and configured without a transfer running behind it.
 */
final class EasyHandleTestDelegate implements EasyHandleDelegate
{
    #[Override]
    public function didReceiveData(string $data): EasyHandleAction
    {
        return EasyHandleAction::proceed;
    }

    #[Override]
    public function didReceiveHeaderData(string $data, int $contentLength): EasyHandleAction
    {
        return EasyHandleAction::proceed;
    }

    #[Override]
    public function fill(mixed $buffer, int $length): EasyHandleWriteBufferResult
    {
        return EasyHandleWriteBufferResult::abort();
    }

    #[Override]
    public function transferCompleted(?Error $error): void
    {
    }

    #[Override]
    public function updateProgressMeter(EasyHandleProgress $progress): void
    {
    }
}

/**
 * Tests the parts of src/Networking/EasyHandle.php that hold no transfer: the CURL
 * option surface, the pause state machine and the CURL-to-URLError translation. The
 * handle is real — curl_init() runs — but nothing is ever transferred, which is what
 * keeps these reachable without a server.
 *
 * Regression guards:
 *  - setURL() reaches the underlying handle, and get() reads back what was set;
 *  - pausing is idempotent and the receive and send halves are independent: pausing one
 *    must not disturb the other, since they map onto separate CURL pause bits;
 *  - urlErrorCode() maps each CURL error family onto its URLError counterpart and
 *    answers null for a code it does not translate, including CURLE_OK — a successful
 *    transfer has no error to report.
 */
final class EasyHandleTest extends TestCase
{
    private function handle(): EasyHandle
    {
        return new EasyHandle(new EasyHandleTestDelegate());
    }

    private function isPaused(EasyHandle $handle, int $option): bool
    {
        // The pause state is private and has no accessor: it is observable only through the CURL handle it drives, so the test reads it directly.
        return new ReflectionProperty(EasyHandle::class, "pauseState")->getValue($handle)->contains($option);
    }

    public function testTheHandleIsBuiltWithACurlHandle(): void
    {
        $handle = $this->handle();

        $this->assertInstanceOf(CurlHandle::class, $handle->rawHandle);
        $this->assertSame(URLSessionWebSocketOperation::cont, $handle->webSocketFlags, "no websocket operation is pending");
    }

    public function testSettingTheUrlReachesTheUnderlyingHandle(): void
    {
        $handle = $this->handle();

        $handle->setURL(new URL("http://127.0.0.1:9/resource"));

        $this->assertSame("http://127.0.0.1:9/resource", $handle->get(CURLINFO_EFFECTIVE_URL));
    }

    public function testTheElapsedTimeIsReadableBeforeAnyTransfer(): void
    {
        $this->assertIsFloat($this->handle()->timeoutIntervalSpent);
    }

    public function testReceiveCanBePausedAndResumed(): void
    {
        $handle = $this->handle();

        $this->assertFalse($this->isPaused($handle, EasyHandlePauseState::receivePaused), "a fresh handle is running");

        $handle->pauseReceive();

        $this->assertTrue($this->isPaused($handle, EasyHandlePauseState::receivePaused));

        $handle->unpauseReceive();

        $this->assertFalse($this->isPaused($handle, EasyHandlePauseState::receivePaused));
    }

    public function testPausingTwiceLeavesTheStateAlone(): void
    {
        $handle = $this->handle();

        $handle->pauseReceive();
        $handle->pauseReceive();

        $this->assertTrue($this->isPaused($handle, EasyHandlePauseState::receivePaused));

        $handle->unpauseReceive();
        $handle->unpauseReceive();

        $this->assertFalse($this->isPaused($handle, EasyHandlePauseState::receivePaused), "resuming an already running handle is a no-op");
    }

    public function testTheSendAndReceiveHalvesArePausedIndependently(): void
    {
        $handle = $this->handle();

        $handle->pauseSend();

        $this->assertTrue($this->isPaused($handle, EasyHandlePauseState::sendPaused));
        $this->assertFalse($this->isPaused($handle, EasyHandlePauseState::receivePaused), "pausing the upload leaves the download running");

        $handle->pauseReceive();
        $handle->unpauseSend();

        $this->assertFalse($this->isPaused($handle, EasyHandlePauseState::sendPaused));
        $this->assertTrue($this->isPaused($handle, EasyHandlePauseState::receivePaused), "resuming the upload leaves the download paused");
    }

    /** @return iterable<string, array{int, int}> */
    public static function errorTranslationProvider(): iterable
    {
        yield "unsupported protocol" => [CURLE_UNSUPPORTED_PROTOCOL, URLErrorUnsupportedURL];
        yield "malformed url" => [CURLE_URL_MALFORMAT, URLErrorBadURL];
        yield "receive error" => [CURLE_RECV_ERROR, URLErrorNetworkConnectionLost];
        yield "partial file" => [CURLE_PARTIAL_FILE, URLErrorNetworkConnectionLost];
        yield "empty reply" => [CURLE_GOT_NOTHING, URLErrorBadServerResponse];
        yield "connection refused" => [CURLE_COULDNT_CONNECT, URLErrorTimedOut];
        yield "timed out" => [CURLE_OPERATION_TIMEDOUT, URLErrorTimedOut];
        yield "unresolvable host" => [CURLE_COULDNT_RESOLVE_HOST, URLErrorCannotFindHost];
        yield "access denied" => [CURLE_REMOTE_ACCESS_DENIED, URLErrorNoPermissionsToReadFile];
        yield "missing remote file" => [CURLE_REMOTE_FILE_NOT_FOUND, URLErrorFileDoesNotExist];
        yield "tls failure" => [CURLE_SSL_CONNECT_ERROR, URLErrorSecureConnectionFailed];
        yield "certificate problem" => [CURLE_SSL_CERTPROBLEM, URLErrorSecureConnectionFailed];
    }

    #[DataProvider("errorTranslationProvider")]
    public function testACurlErrorTranslatesToItsUrlError(int $curlCode, int $expected): void
    {
        $this->assertSame($expected, $this->handle()->urlErrorCode($curlCode));
    }

    public function testAnUntranslatedCurlCodeAnswersNull(): void
    {
        $handle = $this->handle();

        $this->assertNull($handle->urlErrorCode(CURLE_OK), "a successful transfer carries no error");
        $this->assertNull($handle->urlErrorCode(CURLE_FAILED_INIT));
    }

    public function testTheOptionSurfaceIsAcceptedBeforeAnyTransfer(): void
    {
        $handle = $this->handle();
        $handle->setURL(new URL("http://127.0.0.1:9/resource"));

        // Each of these writes a CURL option; none is readable back through curl_getinfo, so the assertion is that configuring a handle end to end raises nothing.
        $handle->setVerboseModeOn(false);
        $handle->setPassHeadersToDataStream(false);
        $handle->setFollowLocation(true);
        $handle->setProgressMeterOff(true);
        $handle->setSkipAllSignalHandling(true);
        $handle->setFailOnHTTPErrorCode(false);
        $handle->setPreferredReceiveBufferSize(65536);
        $handle->setAutomaticBodyDecompression(true);
        $handle->setRequestMethod("POST");
        $handle->setNoBody(false);
        $handle->setUpload(true);
        $handle->setRequestBodyLength(128);
        $handle->setTimeout(30);
        $handle->setCustomHeaders(new Dictionary(["X-First" => "1", "X-Second" => "2"]));
        $handle->setSessionConfig(new URLSessionConfiguration());

        $this->assertSame("http://127.0.0.1:9/resource", $handle->get(CURLINFO_EFFECTIVE_URL), "the handle is still usable afterwards");
    }

    public function testEachAllowedProtocolSetIsAccepted(): void
    {
        $handle = $this->handle();

        $handle->setAllowedProtocolsToHTTPAndHTTPS();
        $handle->setAllowedProtocolsToFTP();
        $handle->setAllowedProtocolsToAll();

        $this->assertInstanceOf(CurlHandle::class, $handle->rawHandle, "the handle survives every protocol restriction");
    }

    public function testDisconnectingIsSafeToRepeat(): void
    {
        $handle = $this->handle();
        $handle->setURL(new URL("http://127.0.0.1:9/resource"));

        $handle->disconnect();
        $handle->disconnect();

        $this->expectNotToPerformAssertions();
    }
}
