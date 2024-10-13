<?php

namespace Sabatier\Foundation\Networking;

use JetBrains\PhpStorm\ExpectedValues;
use Override;
use Sabatier\Foundation\CompareOptions;
use Sabatier\Foundation\Dictionary;
use Sabatier\Foundation\Number;
use Sabatier\Foundation\URL;
use function Sabatier\Foundation\human_readable_value;
use function Sabatier\Foundation\string_has_prefix;
use function Sabatier\Foundation\string_is_equal;

/**
 * The metadata associated with the response to an HTTP protocol URL load request.
 */
class HTTPURLResponse extends URLResponse
{
    public readonly string $httpVersion;
    /** @var int The response's HTTP status code. */
    #[ExpectedValues(valuesFromClass: HTTPStatusCode::class)]
    public readonly int $statusCode;
    /** @var Dictionary<mixed> All HTTP header fields of the response. */
    public readonly Dictionary $allHeaderFields;

    /**
     * Initializes an HTTP URL response object with a status code, protocol version, and response headers.
     * @param URL $url The URL from which the response was generated.
     * @param int $statusCode The HTTP status code to return (404, for example).
     * @param string|null $httpVersion The version of the HTTP response as returned by the server. This is typically represented as "HTTP/1.1".
     * @param Dictionary<mixed>|null $headerFields A dictionary representing the keys and values from the server's response header.
     */
    public function __construct(URL $url, #[ExpectedValues(valuesFromClass: HTTPStatusCode::class)] int $statusCode = HTTPStatusCode::ok, ?string $httpVersion = null, ?Dictionary $headerFields = null)
    {
        $this->statusCode = $statusCode;
        $this->httpVersion = $httpVersion ?? $_SERVER["SERVER_PROTOCOL"] ?? "HTTP/1.1";
        $this->allHeaderFields = (function () use ($headerFields): Dictionary {
            if ($headerFields === null) {
                return new Dictionary();
            }
            /** @var Dictionary<mixed> $canonicalizedFields */
            $canonicalizedFields = new Dictionary();
            foreach ($headerFields as $key => $value) {
                if (empty($key)) {
                    continue;
                }
                if (string_has_prefix($key, "X-", CompareOptions::caseInsensitive)) {
                    $canonicalizedFields[$key] = $value;
                } elseif (string_is_equal($key, "WWW-Authenticate", CompareOptions::caseInsensitive)) {
                    $canonicalizedFields["WWW-Authenticate"] = $value;
                } else {
                    $canonicalizedFields[ucwords($key)] = $value;
                }
            }
            return $canonicalizedFields;
        })();
        $suggestedFilename = $this->suggestedFilename($headerFields);
        $expectedContentLength = $this->expectedContentLength($headerFields);
        $mimeType = null;
        $textEncodingName = null;
        $contentType = $this->contentType($headerFields);
        if ($contentType) {
            $mimeType = $contentType["mimeType"];
            $textEncodingName = $contentType["textEncoding"];
        }
        parent::__construct($url, $mimeType, $expectedContentLength, $textEncodingName, $suggestedFilename);
    }

    private function expectedContentLength(?Dictionary $headerFields): int
    {
        if ($value = $headerFields?->valueForCaseInsensitiveKey("Content-Length")) {
            return (new Number($value))->intValue;
        }
        return URLResponseUnknownLength;
    }

    private function suggestedFilename(?Dictionary $headerFields): string
    {
        if (($value = $headerFields?->valueForCaseInsensitiveKey("Content-Disposition")) && str_contains((string)$value, ";")) {
            [, $part] = explode(";", (string)$value);
            [, $filename] = explode("=", $part);
            return $filename;
        }
        return "Unknown";
    }

    private function contentType(?Dictionary $headerFields): ?array
    {
        if ($value = $headerFields?->valueForCaseInsensitiveKey("Content-Type")) {
            /** @var string $mimeType */
            $mimeType = $value;
            if (str_contains((string)$value, ";")) {
                [$mimeType, $part] = explode(";", (string)$value);
                [, $textEncoding] = explode("=", $part);
                return ["mimeType" => $mimeType, "textEncoding" => $textEncoding];
            }
            return ["mimeType" => $mimeType, "textEncoding" => null];
        }
        return null;
    }

    /**
     * Returns the value that corresponds to the given header field.
     * @param string $field The name of the header field you want to retrieve. The name is case-insensitive.
     * @return string|null The value associated with the given header field, or nil if no value is associated with the field.
     */
    public function valueForHttpHeaderField(string $field): ?string
    {
        if ($value = $this->allHeaderFields->valueForCaseInsensitiveKey($field)) {
            return human_readable_value($value);
        }
        return null;
    }

    /**
     * A localized string suitable for displaying to users that describes the specified status code.
     * @param int $statusCode The HTTP status code.
     * @return string A localized string suitable for displaying to users that describes the specified status code.
     */
    public static function localizedString(#[ExpectedValues(valuesFromClass: HTTPStatusCode::class)] int $statusCode): string
    {
        return match ($statusCode) {
            HTTPStatusCode::continue => "Continue",
            HTTPStatusCode::switchingProtocols => "Switching protocols",
            HTTPStatusCode::ok => "OK",
            HTTPStatusCode::created => "Created",
            HTTPStatusCode::accepted => "Accepted",
            HTTPStatusCode::nonAuthoritativeInformation => "Non authoritative information",
            HTTPStatusCode::noContent => "No content",
            HTTPStatusCode::resetContent => "Reset content",
            HTTPStatusCode::partialContent => "Partial content",
            HTTPStatusCode::multipleChoices => "Multiple choices",
            HTTPStatusCode::movedPermanently => "Moved permanently",
            HTTPStatusCode::found => "Found",
            HTTPStatusCode::seeOther => "See other",
            HTTPStatusCode::notModified => "Not modified",
            HTTPStatusCode::temporaryRedirect => "Temporary redirect",
            HTTPStatusCode::badRequest => "Bad request",
            HTTPStatusCode::unauthorized => "Unauthorized",
            HTTPStatusCode::paymentRequired => "Payment required",
            HTTPStatusCode::forbidden => "Forbidden",
            HTTPStatusCode::notFound => "Not found",
            HTTPStatusCode::methodNotAllowed => "Method not allowed",
            HTTPStatusCode::unacceptable => "Unacceptable",
            HTTPStatusCode::proxyAuthenticationRequired => "Proxy authentication required",
            HTTPStatusCode::requestTimeout => "Request time out",
            HTTPStatusCode::conflict => "Conflict",
            HTTPStatusCode::lengthRequired => "Length required",
            HTTPStatusCode::preconditionFailed => "Precondition failed",
            HTTPStatusCode::requestTooLarge => "Request too large",
            HTTPStatusCode::requestedURLTooLong => "Requested URL too long",
            HTTPStatusCode::unsupportedMediaType => "Unsupported media type",
            HTTPStatusCode::requestedRangeNotSatisfiable => "Requested range not satisfiable",
            HTTPStatusCode::expectationFailed => "Expectation failed",
            HTTPStatusCode::internalServerError => "Internal server error",
            HTTPStatusCode::unimplemented => "Not implemented",
            HTTPStatusCode::badGateway => "Bad gateway",
            HTTPStatusCode::serviceUnavailable => "Service unavailable",
            HTTPStatusCode::gatewayTimeout => "Gateway time out",
            HTTPStatusCode::unsupportedVersion => "Unsupported version",
            default => "Server Error"
        };
    }

    #[Override]
    public function description(): string
    {
        return sprintf("<HTTPURLResponse %s> { URL: %s }{ status: %d, headers {\n%s} }", $this->hash(), $this->url->absoluteString, $this->statusCode, $this->allHeaderFields->mapValues(fn(mixed $value, string $key): string => is_string($value) ? "\"$key\" = \"$value\";\n" : sprintf("\"%s\" = %s;\n", $key, human_readable_value($value)))->values->join(""));
    }
}
