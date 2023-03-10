<?php

namespace Sabatier\Foundation\Networking;

use CurlHandle;
use Sabatier\Foundation\Dictionary;
use Sabatier\Foundation\Error;
use Sabatier\Foundation\ProcessInfo;
use Sabatier\Foundation\UndefinedKeyException;
use Sabatier\Foundation\URL;
use const Sabatier\Foundation\URLErrorBadServerResponse;
use const Sabatier\Foundation\URLErrorBadURL;
use const Sabatier\Foundation\URLErrorCannotFindHost;
use const Sabatier\Foundation\URLErrorNetworkConnectionLost;
use const Sabatier\Foundation\URLErrorTimedOut;
use const Sabatier\Foundation\URLErrorUnknown;
use const Sabatier\Foundation\URLErrorUnsupportedURL;

/**
 * @internal
 * @property-read int $connectFailureErrno
 * @property-read URL|null $redirectURL
 */
final class EasyHandle
{
    public readonly CurlHandle $rawHandle;
    private ?URL $url = null;
    private ?URLSessionConfiguration $configuration = null;
    private EasyHandlePauseState $pauseState;

    public function __construct(public readonly EasyHandleDelegate $delegate)
    {
        $this->rawHandle = curl_init();
        $this->setupCallbacks();
        $this->pauseState = new EasyHandlePauseState();
    }

    public function __destruct()
    {
        curl_close($this->rawHandle);
    }

    public function __get(string $name)
    {
        return match ($name) {
            "connectFailureErrno" => $this->get(CURLINFO_OS_ERRNO),
            "redirectURL" => new URL($this->get(CURLINFO_REDIRECT_URL)),
            default => throw new UndefinedKeyException()
        };
    }

    public function get(int $option): mixed
    {
        /** @psalm-suppress MissingParamType */
        return curl_getinfo($this->rawHandle, $option);
    }

    public function set(mixed $value, int $option): void
    {
        /** @psalm-suppress MissingParamType */
        curl_setopt($this->rawHandle, $option, $value);
    }

    public function setVerboseModeOn(bool $flag): void
    {
        $this->set((int)$flag, CURLOPT_VERBOSE);
    }

    public function setPassHeadersToDataStream(bool $flag): void
    {
        $this->set((int)$flag, CURLOPT_HEADER);
    }

    public function setFollowLocation(bool $flag): void
    {
        $this->set((int)$flag, CURLOPT_FOLLOWLOCATION);
    }

    public function setProgressMeterOff(bool $flag): void
    {
        $this->set((int)$flag, CURLOPT_NOPROGRESS);
    }

    public function setSkipAllSignalHandling(bool $flag): void
    {
        $this->set((int)$flag, CURLOPT_NOSIGNAL);
    }

    public function setFailOnHTTPErrorCode(bool $flag): void
    {
        $this->set((int)$flag, CURLOPT_FAILONERROR);
    }

    public function setURL(URL $url): void
    {
        $this->url = $url;
        $this->set($url->absoluteString, CURLOPT_URL);
    }

    public function setSessionConfig(?URLSessionConfiguration $configuration): void
    {
        $this->configuration = $configuration;
    }

    public function setAllowedProtocolsToHTTPAndHTTPS(): void
    {
        $protocols = CURLPROTO_HTTP | CURLPROTO_HTTPS;
        $this->set($protocols, CURLOPT_PROTOCOLS);
        $this->set($protocols, CURLOPT_REDIR_PROTOCOLS);
        if (($caInfo = ProcessInfo::processInfo()->environment["URLSessionCertificateAuthorityInfoFile"]) && $caInfo !== "INSECURE_SSL_NO_VERIFY") {
            $this->set($caInfo, CURLOPT_CAINFO);
        } else {
            $this->set(0, CURLOPT_SSL_VERIFYPEER);
        }
    }

    public function setAllowedProtocolsToAll(): void
    {
        $this->set(CURLPROTO_ALL, CURLOPT_PROTOCOLS);
        $this->set(CURLPROTO_HTTP | CURLPROTO_HTTPS, CURLOPT_REDIR_PROTOCOLS);
    }

    public function setPreferredReceiveBufferSize(int $size): void
    {
        $this->set(min($size, CURL_MAX_READ_SIZE), CURLOPT_BUFFERSIZE);
    }

    public function setCustomHeaders(Dictionary $headerFields): void
    {
        $this->set($headerFields->mapValues(fn(string $value, string $key): string => $value === "" ? $key : "$key: $value")->values->toArray(), CURLOPT_HTTPHEADER);
    }

    public function setAutomaticBodyDecompression(bool $flag): void
    {
        if ($flag) {
            $this->set("", CURLOPT_ACCEPT_ENCODING);
            $this->set(1, CURLOPT_HTTP_CONTENT_DECODING);
        } else {
            $this->set(null, CURLOPT_ACCEPT_ENCODING);
            $this->set(0, CURLOPT_HTTP_CONTENT_DECODING);
        }
    }

    public function setRequestMethod(string $method): void
    {
        $this->set($method, CURLOPT_CUSTOMREQUEST);
    }

    public function setNoBody(bool $flag): void
    {
        $this->set((int)$flag, CURLOPT_NOBODY);
    }

    public function setUpload(bool $flag): void
    {
        $this->set((int)$flag, CURLOPT_UPLOAD);
    }

    public function setRequestBodyLength(int $length): void
    {
        $this->set($length, CURLOPT_INFILESIZE);
    }

    public function setTimeout(int $timeout): void
    {
        $this->set($timeout, CURLOPT_TIMEOUT);
    }

    public function getTimeoutIntervalSpent(): float
    {
        return $this->get(CURLINFO_TOTAL_TIME);
    }
    
    public function pauseReceive(): void
    {
        $pauseState = $this->pauseState;
        if ($pauseState->contains(EasyHandlePauseState::receivePaused)) {
            return;
        }
        $pauseState->insert(EasyHandlePauseState::receivePaused);
        $pauseState->setState($this);
    }

    public function unpauseReceive(): void
    {
        $pauseState = $this->pauseState;
        if (!$pauseState->contains(EasyHandlePauseState::receivePaused)) {
            return;
        }
        $pauseState->remove(EasyHandlePauseState::receivePaused);
        $pauseState->setState($this);
    }

    public function pauseSend(): void
    {
        $pauseState = $this->pauseState;
        if ($pauseState->contains(EasyHandlePauseState::sendPaused)) {
            return;
        }
        $pauseState->insert(EasyHandlePauseState::sendPaused);
        $pauseState->setState($this);
    }

    public function unpauseSend(): void
    {
        $pauseState = $this->pauseState;
        if (!$pauseState->contains(EasyHandlePauseState::sendPaused)) {
            return;
        }
        $pauseState->remove(EasyHandlePauseState::sendPaused);
        $pauseState->setState($this);
    }

    private function setupCallbacks(): void
    {
        $obj = $this;
        $this->set(true, CURLOPT_RETURNTRANSFER);
        $this->set(fn(mixed $handle, string $data): int => $obj->didReceiveData($data), CURLOPT_WRITEFUNCTION);
        $this->set(fn(mixed $handle, mixed $data, int $size): string => $obj->fill($data), CURLOPT_READFUNCTION);
        $this->set(function (mixed $handle, float $totalBytesExpectedToReceive, float $totalBytesReceived, float $totalBytesExpectedToSend, float $totalBytesSent) use ($obj): int {
            $obj->updateProgressMeter(new EasyHandleProgress($totalBytesSent, $totalBytesExpectedToSend, $totalBytesReceived, $totalBytesExpectedToReceive));
            return CURLE_OK;
        }, CURLOPT_PROGRESSFUNCTION);
        $this->set(fn(mixed $handle, string $data): int => $obj->didReceiveHeaderData($data, curl_getinfo($handle, CURLINFO_CONTENT_LENGTH_DOWNLOAD)), CURLOPT_HEADERFUNCTION);
    }

    public function urlErrorCode(int $easyCode): ?int
    {
        return match ($easyCode) {
            CURLE_UNSUPPORTED_PROTOCOL => URLErrorUnsupportedURL,
            CURLE_URL_MALFORMAT => URLErrorBadURL,
            CURLE_RECV_ERROR, CURLE_SEND_ERROR => URLErrorNetworkConnectionLost,
            CURLE_GOT_NOTHING => URLErrorBadServerResponse,
            CURLE_ABORTED_BY_CALLBACK => URLErrorUnknown,
            CURLE_COULDNT_CONNECT, CURLE_OPERATION_TIMEDOUT => URLErrorTimedOut,
            CURLE_COULDNT_RESOLVE_HOST => URLErrorCannotFindHost,
            default => null
        };
    }

    private function didReceiveData(string $data): int
    {
        return match ($this->delegate->didReceiveData($data)) {
            EasyHandleAction::proceed => strlen($data),
            EasyHandleAction::pause => CURL_WRITEFUNC_PAUSE,
            default => CURLE_OK
        };
    }

    private function fill(mixed $data): string
    {
        $result = $this->delegate->fill($data);
        return match ($result->rawValue) {
            EasyHandleWriteBufferResultRawValue::bytes => (string)$result->bytes,
            EasyHandleWriteBufferResultRawValue::pause => (string)CURL_READFUNC_PAUSE,
            EasyHandleWriteBufferResultRawValue::abort => "",
        };
    }

    public function completedTransfer(?Error $error): void
    {
        $this->delegate->transferCompleted($error);
    }

    private function updateProgressMeter(EasyHandleProgress $progress): void
    {
        $this->delegate->updateProgressMeter($progress);
    }

    private function didReceiveHeaderData(string $data, int $contentLength): int
    {
        $action = match ($this->delegate->didReceiveHeaderData($data, $contentLength)) {
            EasyHandleAction::proceed => strlen($data),
            default => CURLE_OK
        };
        $this->setCookies($data);
        return $action;
    }

    private function setCookies(string $data): void
    {
        if (!($configuration = $this->configuration) || !($url = $this->url) || !($storage = $configuration->httpCookieStorage)) {
            return;
        }
        $headerComponents = explode(":", $data, 1);
        /** @var Dictionary<string> $headerFields */
        $headerFields = new Dictionary();
        if (count($headerComponents) > 1) {
            $headerFields[trim($headerComponents[0])] = trim($headerComponents[1]);
        }
        $cookies = HTTPCookie::cookies($headerFields, $url);
        if ($cookies->isEmpty()) {
            return;
        }
        $storage->setCookies($cookies, $url);
    }
}
