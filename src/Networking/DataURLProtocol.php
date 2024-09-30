<?php

namespace Sabatier\Foundation\Networking;

use Exception;
use Override;
use Sabatier\Foundation\Error;
use function Sabatier\Foundation\fatal_error;
use function Sabatier\Foundation\substring_from_index;
use const Sabatier\Foundation\URLErrorBadURL;
use const Sabatier\Foundation\URLErrorDomain;

/** @internal */
class DataURLProtocol extends URLProtocol
{
    #[Override]
    public static function canInit(URLRequest $request): bool
    {
        return $request->url->scheme === "data";
    }

    /**
     * @throws Exception
     */
    #[Override]
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
            $client->urlProtocolDidFailWithError($this, new Error(URLErrorDomain, URLErrorBadURL));
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
        $mimeType = null;
        $charSet = null;
        $base64 = false;
        $iterator = new PercentDecoder(substring_from_index($dataBody, 5));
        $validate = function (string $mimeType): bool {
            if (str_starts_with($mimeType, "/")) {
                return false;
            }
            $count = 0;
            $lastChar = "";
            $max = strlen($mimeType);
            for ($i = 0; $i < $max; $i++) {
                $c = $mimeType[$i];
                if ($c === "/") {
                    $count += 1;
                }
                if ($count > 1) {
                    return false;
                }
                $lastChar = $c;
            }
            if ($count !== 1) {
                return false;
            }
            return $lastChar !== "/";
        };
        $decodeHeader = function () use ($iterator, $validate, &$mimeType, &$charSet, &$base64): bool {
            $defaultMimeType = "text/plain";
            $part = "";
            $foundCharsetKey = false;
            foreach ($iterator as $element) {
                switch ($element->rawValue) {
                    case PercentDecoderElementRawValue::asciiCharacter:
                        $ch = (string)$element->character;
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
                        break;
                    case PercentDecoderElementRawValue::decodedByte:
                    case PercentDecoderElementRawValue::invalid:
                        return false;
                }
            }
            return false;
        };
        $decodeBase64Body = function () use ($iterator): ?string {
            $base64encoded = "";
            while ($iterator->valid()) {
                $element = $iterator->current();
                switch ($element->rawValue) {
                    case PercentDecoderElementRawValue::asciiCharacter:
                        /** @psalm-suppress PossiblyNullOperand */
                        $base64encoded .= $element->character;
                        break;
                    case PercentDecoderElementRawValue::decodedByte:
                    case PercentDecoderElementRawValue::invalid:
                        return null;
                }
                $iterator->next();
            }
            return base64_decode($base64encoded);
        };
        $decodeStringBody = function () use ($iterator): ?string {
            $data = "";
            while ($iterator->valid()) {
                $element = $iterator->current();
                switch ($element->rawValue) {
                    case PercentDecoderElementRawValue::asciiCharacter:
                        /** @psalm-suppress PossiblyNullOperand */
                        $data .= $element->character;
                        break;
                    case PercentDecoderElementRawValue::decodedByte:
                        $data .= urldecode((string)$element->byte);
                        break;
                    case PercentDecoderElementRawValue::invalid:
                        return null;
                }
                $iterator->next();
            }
            return $data;
        };
        if (!$decodeHeader()) {
            return null;
        }
        /** @psalm-suppress TypeDoesNotContainType, RedundantCondition */
        if (!($data = $base64 ? $decodeBase64Body() : $decodeStringBody())) {
            return null;
        }
        return [new URLResponse($url, $mimeType, strlen($data), $charSet), $data];
    }

    #[Override]
    public function stopLoading(): void
    {
    }
}
