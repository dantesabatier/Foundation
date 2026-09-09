<?php

declare(strict_types=1);

namespace Sabatier\Foundation\Tests;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Sabatier\Foundation\Error;
use Sabatier\Foundation\Networking\AuthParameter;
use Sabatier\Foundation\Networking\FTPHeaderCode;
use Sabatier\Foundation\Networking\HTTPURLResponse;
use Sabatier\Foundation\Networking\InternalState;
use Sabatier\Foundation\Networking\InternalStateRawValue;
use Sabatier\Foundation\Networking\TaskBody;
use Sabatier\Foundation\Networking\TaskBodyRawValue;
use Sabatier\Foundation\Networking\TransferState;
use Sabatier\Foundation\Networking\URLResponse;
use Sabatier\Foundation\URL;

use const Sabatier\Foundation\CocoaErrorDomain;
use const Sabatier\Foundation\FileNoSuchFileError;
use const Sabatier\Foundation\FileReadNoPermissionError;
use const Sabatier\Foundation\URLErrorDomain;
use const Sabatier\Foundation\URLErrorFileDoesNotExist;
use const Sabatier\Foundation\URLErrorNoPermissionsToReadFile;
use const Sabatier\Foundation\URLErrorUnknown;

/**
 * Tests the networking types that carry no transport of their own: the transfer state
 * machine, the request body descriptor, the task's internal state, and the auth
 * parameter parser. All of them are pure value logic, so they are reachable without a
 * server — unlike EasyHandle and the URL protocols, which need a live CURL transfer.
 *
 * Regression guards:
 *  - TransferState accumulates header lines and materializes the response only once the
 *    header is complete: a blank line for HTTP, a data-connection code for FTP. Each
 *    append answers a new state, so a partially parsed transfer is never mutated;
 *  - an FTP transfer-completed line is not header data and leaves the state untouched;
 *  - InternalState reports whether the easy handle belongs to the multi handle and
 *    whether it is paused, which is what drives registration with CURL;
 *  - TaskBody knows its own length per kind, and maps a file-system error onto the
 *    URL error the task reports;
 *  - AuthParameter skips a component that is not a name=value pair rather than
 *    producing a malformed parameter.
 */
final class NetworkingTransferStateTest extends TestCase
{
    private function httpState(): TransferState
    {
        return new TransferState(new URL("https://example.test/resource"));
    }

    public function testAnHttpHeaderCompletesOnTheBlankLine(): void
    {
        $state = $this->httpState();

        $withStatus = $state->byAppendingHTTP("HTTP/1.1 200 OK\r\n");

        $this->assertFalse($withStatus->isHeaderComplete(), "a status line alone is not a complete header");

        $withField = $withStatus->byAppendingHTTP("Content-Type: text/plain\r\n");

        $this->assertFalse($withField->isHeaderComplete());

        $completed = $withField->byAppendingHTTP("\r\n");

        $this->assertTrue($completed->isHeaderComplete(), "the blank line closes the header");
        $this->assertInstanceOf(HTTPURLResponse::class, $completed->response);
    }

    public function testAppendingAHeaderLineLeavesTheReceiverAlone(): void
    {
        $state = $this->httpState();

        $appended = $state->byAppendingHTTP("HTTP/1.1 200 OK\r\n");

        $this->assertNotSame($state, $appended, "each append answers a new state");
        $this->assertFalse($state->isHeaderComplete());
        $this->assertNull($state->response);
    }

    public function testTheCompletedHttpResponseCarriesTheParsedHeaders(): void
    {
        $completed = $this->httpState()
            ->byAppendingHTTP("HTTP/1.1 404 Not Found\r\n")
            ->byAppendingHTTP("Content-Type: application/json\r\n")
            ->byAppendingHTTP("\r\n");

        $response = $completed->response;

        $this->assertInstanceOf(HTTPURLResponse::class, $response);
        $this->assertSame(404, $response->statusCode);
        $this->assertSame("application/json", $response->mimeType);
    }

    public function testAnFtpHeaderCompletesOnTheDataConnectionCode(): void
    {
        $state = new TransferState(new URL("ftp://example.test/file"));

        $greeted = $state->byAppendingFTP("220 service ready\r\n", 42);

        $this->assertFalse($greeted->isHeaderComplete());

        $opened = $greeted->byAppendingFTP(FTPHeaderCode::dataConnectionOpen->value . " opening\r\n", 42);

        $this->assertTrue($opened->isHeaderComplete());
        $this->assertInstanceOf(URLResponse::class, $opened->response);
        $this->assertSame(42, $opened->response->expectedContentLength, "the caller's content length reaches the response");
    }

    public function testAnFtpTransferCompletedLineIsNotHeaderData(): void
    {
        $state = new TransferState(new URL("ftp://example.test/file"));

        $unchanged = $state->byAppendingFTP(FTPHeaderCode::transferCompleted->value . " transfer complete\r\n", 42);

        $this->assertSame($state, $unchanged, "the completion code is answered with the very same state");
    }

    /** @return iterable<string, array{InternalState, bool, bool}> */
    public static function internalStateProvider(): iterable
    {
        $transferState = new TransferState(new URL("https://example.test/resource"));

        yield "initial" => [InternalState::initial(), false, false];
        yield "transfer ready" => [InternalState::transferReady($transferState), false, false];
        yield "transfer in progress" => [InternalState::transferInProgress($transferState), true, false];
        yield "waiting for response handler" => [InternalState::waitingForResponseCompletionHandler($transferState), true, true];
        yield "transfer failed" => [InternalState::transferFailed(), false, false];
        yield "task completed" => [InternalState::taskCompleted(), false, false];
    }

    #[DataProvider("internalStateProvider")]
    public function testInternalStateReportsItsCurlRegistration(InternalState $state, bool $isAdded, bool $isPaused): void
    {
        $this->assertSame($isAdded, $state->isEasyHandleAddedToMultiHandle());
        $this->assertSame($isPaused, $state->isEasyHandlePaused());
    }

    public function testInternalStateCarriesThePayloadOfItsCase(): void
    {
        $transferState = new TransferState(new URL("https://example.test/resource"));

        $ready = InternalState::transferReady($transferState);

        $this->assertSame(InternalStateRawValue::transferReady, $ready->rawValue);
        $this->assertSame($transferState, $ready->transferState);
        $this->assertNull($ready->response, "a case carries only the payload it was built with");
        $this->assertSame(InternalStateRawValue::initial, InternalState::initial()->rawValue);
    }

    /** @return iterable<string, array{TaskBody, TaskBodyRawValue, int}> */
    public static function taskBodyProvider(): iterable
    {
        yield "none" => [TaskBody::none(), TaskBodyRawValue::none, 0];
        yield "data" => [TaskBody::data("hello"), TaskBodyRawValue::data, 5];
        yield "empty data" => [TaskBody::data(""), TaskBodyRawValue::data, 0];
        yield "stream" => [TaskBody::stream(null), TaskBodyRawValue::stream, 0];
    }

    #[DataProvider("taskBodyProvider")]
    public function testTaskBodyKnowsItsOwnLength(TaskBody $body, TaskBodyRawValue $rawValue, int $length): void
    {
        $this->assertSame($rawValue, $body->rawValue);
        $this->assertSame($length, $body->getBodyLength());
    }

    public function testAFileBodyRemembersItsUrl(): void
    {
        $url = new URL("file:///tmp/upload.bin");

        $body = TaskBody::file($url);

        $this->assertSame(TaskBodyRawValue::file, $body->rawValue);
        $this->assertSame($url, $body->fileURL);
    }

    /** @return iterable<string, array{string, int, int}> */
    public static function bodyErrorProvider(): iterable
    {
        yield "missing file" => [CocoaErrorDomain, FileNoSuchFileError, URLErrorFileDoesNotExist];
        yield "unreadable file" => [CocoaErrorDomain, FileReadNoPermissionError, URLErrorNoPermissionsToReadFile];
        yield "other cocoa error" => [CocoaErrorDomain, 999999, URLErrorUnknown];
        yield "other domain" => [URLErrorDomain, 1, URLErrorUnknown];
    }

    #[DataProvider("bodyErrorProvider")]
    public function testAFileSystemErrorMapsOntoAUrlError(string $domain, int $code, int $expected): void
    {
        $this->assertSame($expected, TaskBody::none()->errorCode(new Error($domain, $code)));
    }

    public function testAuthParametersAreParsedByName(): void
    {
        $parameters = AuthParameter::parameters("realm=\"api\", qop=auth, nonce=abc");

        $this->assertSame(3, $parameters->count);
        $this->assertSame("realm", $parameters[0]->name);
        $this->assertSame("\"api\"", $parameters[0]->value, "the quoting is left for the caller to strip");
        $this->assertSame("qop", $parameters[1]->name);
        $this->assertSame("auth", $parameters[1]->value);
        $this->assertSame("nonce", $parameters[2]->name);
    }

    /** @return iterable<string, array{string, int}> */
    public static function malformedAuthProvider(): iterable
    {
        yield "empty" => ["", 0];
        yield "no assignment" => ["Basic", 0];
        yield "one of three is malformed" => ["a=1, malformed, b=2", 2];
    }

    #[DataProvider("malformedAuthProvider")]
    public function testAComponentWithoutAnAssignmentIsSkipped(string $header, int $expected): void
    {
        $this->assertSame($expected, AuthParameter::parameters($header)->count);
    }
}
