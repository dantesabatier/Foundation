<?php

namespace Sabatier\Foundation\Networking;

use CurlHandle;
use Exception;
use Sabatier\Foundation\ArrayClass;
use Sabatier\Foundation\Dictionary;
use Sabatier\Foundation\Error;
use Sabatier\Foundation\ProcessInfo;
use Sabatier\Foundation\UndefinedKeyException;
use Sabatier\Foundation\URL;
use function Sabatier\Foundation\fatal_error;
use const Sabatier\Foundation\URLErrorBadServerResponse;
use const Sabatier\Foundation\URLErrorBadURL;
use const Sabatier\Foundation\URLErrorCannotFindHost;
use const Sabatier\Foundation\URLErrorNetworkConnectionLost;
use const Sabatier\Foundation\URLErrorTimedOut;
use const Sabatier\Foundation\URLErrorUnknown;
use const Sabatier\Foundation\URLErrorUnsupportedURL;

/** @internal */
final class EasyHandle
{
    public readonly CurlHandle $rawHandle;
    public readonly int $connectFailureErrno;
    public readonly ?URL $redirectURL;
    private ?URL $url = null;
    /** @var Dictionary<string> */
    private Dictionary $allHeaderFields;
    private ?URLSessionConfiguration $configuration = null;
    private EasyHandlePauseState $pauseState;
    private URLSessionWebSocketOperation $operation = URLSessionWebSocketOperation::cont;
    public readonly bool $isWebSocketHandle;
    private mixed $socket;

    public function __construct(public readonly EasyHandleDelegate $delegate)
    {
        unset($this->rawHandle);
        unset($this->connectFailureErrno);
        unset($this->redirectURL);
        unset($this->pauseState);
        unset($this->allHeaderFields);
        unset($this->isWebSocketHandle);
    }

    public function __destruct()
    {
        $this->disconnect();
    }

    public function __get(string $name)
    {
        return $this->$name = match ($name) {
            "rawHandle" => curl_init(),
            "connectFailureErrno" => $this->get(CURLINFO_OS_ERRNO),
            "redirectURL" => ($s = $this->get(CURLINFO_REDIRECT_URL)) ? new URL($s) : null,
            "allHeaderFields" => new Dictionary(),
            "isWebSocketHandle" => $this->delegate instanceof WebSocketURLProtocol,
            default => throw new UndefinedKeyException()
        };
    }

    public function get(int $option): mixed
    {
        if ($this->isWebSocketHandle) {
            return null;
        }
        return curl_getinfo($this->rawHandle, $option);
    }

    public function set(mixed $value, int $option): void
    {
        if (!$this->isWebSocketHandle) {
            curl_setopt($this->rawHandle, $option, $value);
        }
    }

    public function setVerboseModeOn(bool $flag): void
    {
        $this->set($flag, CURLOPT_VERBOSE);
    }

    public function setPassHeadersToDataStream(bool $flag): void
    {
        $this->set($flag, CURLOPT_HEADER);
    }

    public function setFollowLocation(bool $flag): void
    {
        $this->set($flag, CURLOPT_FOLLOWLOCATION);
    }

    public function setProgressMeterOff(bool $flag): void
    {
        $this->set($flag, CURLOPT_NOPROGRESS);
    }

    public function setSkipAllSignalHandling(bool $flag): void
    {
        $this->set($flag, CURLOPT_NOSIGNAL);
    }

    public function setFailOnHTTPErrorCode(bool $flag): void
    {
        $this->set($flag, CURLOPT_FAILONERROR);
    }

    public function setURL(URL $url): void
    {
        $this->url = $url;
        if ($this->isWebSocketHandle) {
            $authority = (string)$url->host;
            if (($user = $url->user) && ($password = $url->password)) {
                $authority = "$user:$password@$authority";
            }
            if ($port = $url->port) {
                $authority .= ":" . $port;
            }
            /** @noinspection PhpUnhandledExceptionInspection */
            $key = base64_encode(random_bytes(16));
            $this->allHeaderFields = new Dictionary([
                "Host" => $authority,
                "Upgrade" => "WebSocket",
                "Connection" => "Upgrade",
                "Sec-WebSocket-Key" => $key,
                "Sec-WebSocket-Version" => "13"
            ]);
            $this->socket = stream_socket_client($url->absoluteString);
            return;
        }
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
            $this->set(false, CURLOPT_SSL_VERIFYPEER);
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

    /**
     * @param Dictionary<string> $headerFields
     */
    public function setCustomHeaders(Dictionary $headerFields): void
    {
        $this->allHeaderFields->merge($headerFields);
        if (!$this->isWebSocketHandle) {
            $this->set($headerFields->mapValues(fn(string $value, string $key): string => $value === "" ? $key : "$key: $value")->values->toArray(), CURLOPT_HTTPHEADER);
        }
    }

    public function setAutomaticBodyDecompression(bool $flag): void
    {
        if ($flag) {
            $this->set("", CURLOPT_ACCEPT_ENCODING);
            $this->set(true, CURLOPT_HTTP_CONTENT_DECODING);
        } else {
            $this->set(null, CURLOPT_ACCEPT_ENCODING);
            $this->set(false, CURLOPT_HTTP_CONTENT_DECODING);
        }
    }

    public function setRequestMethod(string $method): void
    {
        $this->set($method, CURLOPT_CUSTOMREQUEST);
    }

    public function setNoBody(bool $flag): void
    {
        $this->set($flag, CURLOPT_NOBODY);
    }

    public function setUpload(bool $flag): void
    {
        $this->set($flag, CURLOPT_UPLOAD);
    }

    public function setRequestBodyLength(int $length): void
    {
        $this->set($length, CURLOPT_INFILESIZE);
    }

    public function setTimeout(int $timeout): void
    {
        if ($this->isWebSocketHandle) {
            stream_set_timeout($this->socket, $timeout);
        } else {
            $this->set($timeout, CURLOPT_TIMEOUT);
        }
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

    public function setupCallbacks(): void
    {
        $this->set(true, CURLOPT_RETURNTRANSFER);
        $this->set(fn(CurlHandle $handle, string $data): int => $this->didReceiveData($data), CURLOPT_WRITEFUNCTION);
        $this->set(fn(CurlHandle $handle, mixed $data, int $size): string => $this->fill($data), CURLOPT_READFUNCTION);
        $this->set(function (CurlHandle $handle, float $totalBytesExpectedToReceive, float $totalBytesReceived, float $totalBytesExpectedToSend, float $totalBytesSent): int {
            $this->updateProgressMeter(new EasyHandleProgress($totalBytesSent, $totalBytesExpectedToSend, $totalBytesReceived, $totalBytesExpectedToReceive));
            return CURLE_OK;
        }, CURLOPT_PROGRESSFUNCTION);
        $this->set(fn(CurlHandle $handle, string $data): int => $this->didReceiveHeaderData($data, curl_getinfo($handle, CURLINFO_CONTENT_LENGTH_DOWNLOAD)), CURLOPT_HEADERFUNCTION);
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

    private function fill(mixed $buffer): string
    {
        $result = $this->delegate->fill($buffer);
        return match ($result->rawValue) {
            EasyHandleWriteBufferResultRawValue::bytes => $result->bytes,
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
        $this->setCookies($data);
        return match ($this->delegate->didReceiveHeaderData($data, $contentLength)) {
            EasyHandleAction::proceed => strlen($data),
            default => CURLE_OK
        };
    }

    private function setCookies(string $data): void
    {
        if (!($url = $this->url) || !($storage = $this->configuration?->httpCookieStorage)) {
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

    public function connect(): void
    {
        $header = "GET {$this->url?->absoluteString} HTTP/1.1\r\n";
        $header .= $this->allHeaderFields->mapValues(fn(string $value, string $key): string => "$key: $value")->values->join("\r\n");
        $header .= "\r\n\r\n";
        fwrite($this->socket, $header);
        $buffer = "";
        do {
            $data = $this->fill($this->socket);
            $buffer .= $data;
            $this->didReceiveHeaderData($data, strlen($data));
        } while (substr_count($buffer, "\r\n\r\n") == 0);
    }

    public function disconnect(): void
    {
        if ($this->isWebSocketHandle) {
            fclose($this->socket);
        } else {
            curl_close($this->rawHandle);
        }
    }

    public function getWebSocketFlags(): URLSessionWebSocketOperation
    {
        return $this->operation;
    }

    /**
     * @return array{string, URLSessionWebSocketOperation}
     * @throws Exception
     */
    public function receiveWebSocketsData(): array
    {
        $fn = function (int $length): string {
            $data = "";
            while (strlen($data) < $length) {
                if (!($buffer = fread($this->socket, $length - strlen($data)))) {
                    if (stream_get_meta_data($this->socket)["timed_out"]) {
                        fatal_error("Connection timeout");
                    }
                    fatal_error("Unexpected message received from server");
                }
                $data .= $buffer;
            }
            return $data;
        };
        $payload = "";
        do {
            $data = $fn(2);
            [$byte1, $byte2] = array_values(unpack("C*", $data));
            $isFinal = (bool)($byte1 & 0b10000000);
            $isMasked = (bool)($byte2 & 0b10000000);
            $length = $byte2 & 0b01111111;
            if ($length > 125) {
                if ($length === 126) {
                    $data = $fn(2);
                    $length = current(unpack("n", $data));
                } else {
                    $data = $fn(8);
                    $length = current(unpack("J", $data));
                }
            }
            $mask = "";
            if ($isMasked) {
                $mask = $fn(4);
            }
            if ($length > 0) {
                $data = $fn($length);
                if ($isMasked) {
                    for ($i = 0; $i < $length; $i++) {
                        $payload .= ($data[$i] ^ $mask[$i % 4]);
                    }
                } else {
                    $payload = $data;
                }
            }
            $operation = URLSessionWebSocketOperation::from($byte1 & 0b00001111);
            switch ($operation) {
                case URLSessionWebSocketOperation::ping:
                    $this->sendWebSocketsData($payload, URLSessionWebSocketOperation::pong);
                    break;
                case URLSessionWebSocketOperation::close:
                case URLSessionWebSocketOperation::pong:
                case URLSessionWebSocketOperation::cont:
                case URLSessionWebSocketOperation::text:
                case URLSessionWebSocketOperation::binary:
                    break;
            }
            $this->operation = $operation;
        } while (!$isFinal);
        return [$payload, $operation];
    }

    public function sendWebSocketsData(string $data, URLSessionWebSocketOperation $operation): void
    {
        $parts = new ArrayClass(str_split($data, 4096) ?: [""]);
        /** @var ArrayClass<array{string, URLSessionWebSocketOperation, bool, bool}> $frames */
        $frames = $parts->map(fn(string $e, int $i): array => [$e, $i === 0 ? $operation : URLSessionWebSocketOperation::cont, $i === $parts->indexBefore($parts->endIndex()), true]);
        foreach ($frames as $frame) {
            $data = "";
            [$payload, $operation, $isFinal, $isMasked] = $frame;
            $byte1 = $isFinal ? 0b10000000 : 0b00000000;
            $byte1 |= $operation->value;
            $byte2 = $isMasked ? 0b10000000 : 0b00000000;
            $data .= pack("C", $byte1);
            $length = strlen($payload);
            if ($length > 65535) {
                $data .= pack("C", $byte2 | 0b01111111);
                $data .= pack("J", $length);
            } elseif ($length > 125) {
                $data .= pack("C", $byte2 | 0b01111110);
                $data .= pack("n", $length);
            } else {
                $data .= pack("C", $byte2 | $length);
            }
            if ($isMasked) {
                $mask = "";
                for ($i = 0; $i < 4; $i++) {
                    $mask .= chr(rand(0, 255));
                }
                $data .= $mask;
                for ($i = 0; $i < $length; $i++) {
                    $data .= $payload[$i] ^ $mask[$i % 4];
                }
            } else {
                $data .= $payload;
            }
            fwrite($this->socket, $data);
        }
    }

    public static function supportsWebSockets(): bool
    {
        return (new ArrayClass(stream_get_transports()))->contains(fn(string $e): bool => $e === "tpc" || $e === "ssl");
    }
}
