<?php

namespace Sabatier\Foundation\Networking;

use Sabatier\Foundation\ArrayClass;
use Sabatier\Foundation\Dictionary;
use Sabatier\Foundation\URL;

/** @internal */
class WebSocketClient
{
    private URL $url;
    private Dictionary $additionalHeaders;
    private mixed $socket;
    public URLSessionWebSocketOperationCode $code = URLSessionWebSocketOperationCode::cont;

    public function __construct(public readonly EasyHandleDelegate $delegate)
    {
        $this->additionalHeaders = new Dictionary();
    }

    public function setURL(URL $url): void
    {
        $authority = (string)$url->host;
        if (($user = $url->user) && ($password = $url->password)) {
            $authority = "$user:$password@$authority";
        }
        if ($port = $url->port) {
            $authority .= ':' . $port;
        }
        /** @noinspection PhpUnhandledExceptionInspection */
        $key = base64_encode(random_bytes(16));
        $this->additionalHeaders = new Dictionary([
            "Host" => $authority,
            "Upgrade" => "WebSocket",
            "Connection" => "Upgrade",
            "Sec-WebSocket-Key" => $key,
            "Sec-WebSocket-Version" => "13"
        ]);
        $this->url = $url;
        $this->socket = stream_socket_client($url->absoluteString);
    }

    public function setPreferredReceiveBufferSize(int $size): void
    {
        stream_set_chunk_size($this->socket, $size);
    }

    public function setCustomHeaders(Dictionary $headerFields): void
    {
        $this->additionalHeaders->merge($headerFields);
    }

    public function setRequestBodyLength(int $length): void
    {
    }

    public function setTimeout(int $timeout): void
    {
        stream_set_timeout($this->socket, $timeout);
    }

    public function start(): void
    {
        $header = "GET {$this->url->absoluteString} HTTP/1.1\r\n";
        $header .= $this->additionalHeaders->mapValues(fn(string $value, string $key): string => "$key: $value")->values->join("\r\n");
        $header .= "\r\n\r\n";
        fwrite($this->socket, $header);
        while (!feof($this->socket)) {
            $result = $this->delegate->fill($this->socket);
            if (!($data = match ($result->rawValue) {
                EasyHandleWriteBufferResultRawValue::bytes => $result->bytes,
                EasyHandleWriteBufferResultRawValue::pause, EasyHandleWriteBufferResultRawValue::abort => null,
            })) {
                break;
            }
            $this->delegate->didReceiveHeaderData($data, strlen($data));
        }
    }

    public function stop(): void
    {
        fclose($this->socket);
    }

    public function receive(): string
    {
        $fn = function (int $length): string {
            $data = "";
            while (strlen($data) < $length && ($result = fread($this->socket, $length))) {
                $data .= $result;
            }
            return $data;
        };
        do {
            $data = $fn(2);
            $components = array_values(unpack('C*', $data));
            if (empty($components)) {
                break;
            }
            [$byte1, $byte2] = $components;
            $final = (bool)($byte1 & 0b10000000);
            $isMasked = (bool)($byte2 & 0b10000000);
            $length = $byte2 & 0b01111111;
            if ($length > 125) {
                if ($length === 126) {
                    $data = $fn(2);
                    $length = current(unpack('n', $data));
                } else {
                    $data = $fn(8);
                    $length = current(unpack('J', $data));
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
                        $length .= ($data[$i] ^ $mask[$i % 4]);
                    }
                } else {
                    $length = $data;
                }
            }
            $code = URLSessionWebSocketOperationCode::from($byte1 & 0b00001111);
            switch ($code) {
                case URLSessionWebSocketOperationCode::ping:
                case URLSessionWebSocketOperationCode::close:
                    //TODO: implement this
                    break;
                case URLSessionWebSocketOperationCode::pong:
                case URLSessionWebSocketOperationCode::cont:
                case URLSessionWebSocketOperationCode::text:
                case URLSessionWebSocketOperationCode::binary:
                    break;
            }
            $this->code = $code;
            error_log($data);
        } while (!$final);
        return "";
    }

    public function send(string $data, URLSessionWebSocketOperationCode $code): void
    {
        $parts = new ArrayClass(str_split($data, 4096));
        $frames = $parts->map(fn(string $string, int $index): WebSocketFrame => new WebSocketFrame($string, $index === 0 ? $code : URLSessionWebSocketOperationCode::cont, $index === $parts->indexBefore($parts->endIndex())));
        foreach ($frames as $frame) {
            $data = "";
            $payload = $frame->payload;
            $isMasked = $frame->isMasked;
            $byte1 = $frame->isFinal ? 0b10000000 : 0b00000000;
            $byte1 |= $frame->operationCode->value;
            $byte2 = $isMasked ? 0b10000000 : 0b00000000;
            $data .= pack('C', $byte1);
            $length = strlen($payload);
            if ($length > 65535) {
                $data .= pack('C', $byte2 | 0b01111111);
                $data .= pack('J', $length);
            } elseif ($length > 125) {
                $data .= pack('C', $byte2 | 0b01111110);
                $data .= pack('n', $length);
            } else {
                $data .= pack('C', $byte2 | $length);
            }
            if ($isMasked) {
                $isMasked = "";
                for ($i = 0; $i < 4; $i++) {
                    $isMasked .= chr(rand(0, 255));
                }
                $data .= $isMasked;
                for ($i = 0; $i < $length; $i++) {
                    $data .= $payload[$i] ^ $isMasked[$i % 4];
                }
            } else {
                $data .= $payload;
            }
            fwrite($this->socket, $data);
        }
    }
}
