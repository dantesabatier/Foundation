<?php

namespace Sabatier\Foundation\Networking;

use Exception;
use Sabatier\Foundation\Error;
use Sabatier\Foundation\Scanner;
use function Sabatier\Foundation\fatal_error;
use function Sabatier\Foundation\substring_from_index;
use const Sabatier\Foundation\URLErrorBadURL;
use const Sabatier\Foundation\URLErrorDomain;

/** @internal */
class DataURLProtocol extends URLProtocol
{
    public static function canInit(URLRequest $request): bool
    {
        return $request->url->scheme === "data";
    }

    /**
     * @throws Exception
     */
    public function startLoading(): void
    {
        if (!($client = $this->client)) {
            fatal_error("No URLProtocol client set");
        }
        if ($decoded = $this->decodeURI()) {
            [$response, $data] = $decoded;
            $client->urlProtocolDidReceiveCacheStoragePolicy($this, $response, URLCacheStoragePolicy::allowed);
            $client->urlProtocolDidLoad($this, $data);
            $client->urlProtocolDidFinishLoading($this);
        } else {
            $session = $this->task->session;
            $delegate = $session->delegate;
            if ($delegate instanceof URLSessionTaskDelegate) {
                $delegate->urlSessionTaskDidComplete($session, $this->task, new Error(URLErrorDomain, URLErrorBadURL));
            }
        }
    }

    /**
     * @return array{URLResponse, string}|null
     */
    private function decodeURI(): ?array
    {
        $url = $this->request->url;
        $dataBody = $url->absoluteString;
        if (!str_starts_with($dataBody, "data:")) {
            return null;
        }
        $scanner = new Scanner(substring_from_index(urldecode($dataBody), 5));
        $mimeType = null;
        $charSet = null;
        $base64 = false;
        $validate = function (string $mimeType): bool {
            if (str_starts_with($mimeType, "/")) {
                return false;
            }
            $count = 0;
            $lastChar = "";
            foreach (str_split($mimeType) as $ch) {
                if ($ch === "/") {
                    $count += 1;
                }
                if ($count > 1) {
                    return false;
                }
                $lastChar = $ch;
            }
            if ($count !== 1) {
                return false;
            }
            return $lastChar !== "/";
        };
        $decodeHeader = function () use ($scanner, $validate, &$mimeType, &$charSet, &$base64): bool {
            $defaultMimeType = "text/plain";
            $part = "";
            $foundCharsetKey = false;
            while (!$scanner->isAtEnd) {
                $ch = $scanner->string[$scanner->scanLocation];
                switch ($ch) {
                    case ",":
                        if ($foundCharsetKey) {
                            $charSet = $part;
                        } else {
                            $base64 = $part === ";base64";
                        }
                        if ($mimeType === null || !$validate($mimeType)) {
                            $mimeType = $defaultMimeType;
                        }
                        $scanner->scanLocation += 1;
                        return true;
                    case ";":
                        if ($mimeType === null) {
                            if (str_contains($part, "/")) {
                                $mimeType = $part;
                            } else {
                                $mimeType = $defaultMimeType;
                            }
                        }
                        if ($foundCharsetKey) {
                            $charSet = $part;
                            $foundCharsetKey = false;
                        }
                        $part = ";";
                        break;
                    case "=":
                        if ($mimeType === null) {
                            $mimeType = $defaultMimeType;
                        } elseif ($part === ";charset" && $charSet === null) {
                            $foundCharsetKey = true;
                            $part = "";
                        }
                        break;
                    default:
                        $part .= $ch;
                        break;
                }
                $scanner->scanLocation += 1;
            }
            return false;
        };
        if (!$decodeHeader()) {
            return null;
        }
        $data = substring_from_index($scanner->string, $scanner->scanLocation);
        $data = $base64 ? base64_decode($data) : $data;
        return [new URLResponse($url, $mimeType, strlen($data), $charSet), $data];
    }

    public function stopLoading(): void
    {
    }
}
