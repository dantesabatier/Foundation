<?php

namespace Sabatier\Foundation\Networking;

use Exception;
use Sabatier\Foundation\Dictionary;
use Sabatier\Foundation\URL;
use Sabatier\Foundation\URLComponents;
use function Sabatier\Foundation\fatal_error;

/** @internal */
class WebSocketURLProtocol extends URLProtocol
{
    private mixed $stream;
    
    public static function canInit(URLRequest $request): bool
    {
        return match ($request->url->scheme) {
            "ws", "wss" => true,
            default => false
        };
    }

    /**
     * @throws Exception
     */
    public function startLoading(): void
    {
        $absoluteURL = $this->request->url->absoluteURL;
        $components = new URLComponents($absoluteURL->absoluteString);
        $components->scheme = $absoluteURL->scheme === "wss" ? "ssl" : "tcp";
        $components->port = $absoluteURL->port ?? $absoluteURL->scheme === "wss" ? 443 : 80;
        /** @var URL $url */
        $url = $components->url;
        $authority = (string)$url->host;
        if (($user = $url->user) && ($password = $url->password)) {
            $authority = "$user:$password@$authority";
        }
        if ($port = $url->port) {
            $authority .= ':' . $port;
        }
        $path = $url->path;
        if ($query = $url->query) {
            $path .= "?$query";
        }
        $key = base64_encode(random_bytes(16));
        $headers = new Dictionary([
            "Host" => $authority,
            "Upgrade" => "WebSocket",
            "Connection" => "Upgrade",
            "Sec-WebSocket-Key" => $key,
            "Sec-WebSocket-Version" => "13",
        ]);
        $header = "GET $path HTTP/1.1\r\n";
        $header .= $headers->mapValues(fn(string $value, string $key): string => "$key: $value")->values->join("\r\n");
        $header .= "\r\n\r\n";
        if (!($this->stream = stream_socket_client($url->absoluteString, $code, $message, $this->request->timeoutInterval))) {
            fatal_error("$code $message");
        }
        fwrite($this->stream, $header);
        $response = "";
        do {
            $response .= fgets($this->stream, 1024);
        } while (substr_count($response, "\r\n\r\n") === 0);
        if (!preg_match("#Sec-WebSocket-Accept:\\s(.*)\$#mUi", $response, $matches)) {
            fatal_error();
        }
        $value = trim($matches[1]);
        $expected = base64_encode(pack('H*', sha1($key . "258EAFA5-E914-47DA-95CA-C5AB0DC85B11")));
        if ($value !== $expected) {
            fatal_error();
        }
        /** @var URLSessionWebSocketTask $task */
        $task = $this->task;
        $task->handshakeCompleted = true;
    }

    public function stopLoading(): void
    {
        fclose($this->stream);
    }

    public function send(string $data, URLSessionWebSocketOperationCode $code): void
    {
        error_log(sprintf("%s(%s, %s)", __METHOD__, $data, $code->name));
    }
}
