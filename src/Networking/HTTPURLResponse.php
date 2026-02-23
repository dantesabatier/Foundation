<?php

namespace Sabatier\Foundation\Networking;

use JetBrains\PhpStorm\ExpectedValues;
use Override;
use Sabatier\Foundation\CompareOptions;
use Sabatier\Foundation\Dictionary;
use Sabatier\Foundation\Number;
use Sabatier\Foundation\URL;
use function Sabatier\Foundation\human_readable_value;
use function Sabatier\Foundation\localized_string;
use function Sabatier\Foundation\string_has_prefix;
use function Sabatier\Foundation\string_is_equal;

/**
 * The metadata associated with the response to an HTTP protocol URL load request.
 */
class HTTPURLResponse extends URLResponse
{
    private(set) string $httpVersion;
    /** @var int The response's HTTP status code. */
    #[ExpectedValues(valuesFromClass: HTTPStatusCode::class)]
    private(set) int $statusCode;
    /** @var Dictionary<mixed> All HTTP header fields of the response. */
    private(set) Dictionary $allHeaderFields;
    #[Override]
    public string $description {
        get => sprintf("<HTTPURLResponse %s> { URL: %s }{ status: %d, headers {\n%s} }", $this->hash, $this->url->absoluteString, $this->statusCode, $this->allHeaderFields->mapValues(fn(mixed $value, string $key): string => is_string($value) ? "\"$key\" = \"$value\";\n" : sprintf("\"%s\" = %s;\n", $key, human_readable_value($value)))->values->join(""));
    }

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
        $this->allHeaderFields = $this->canonicalizedFields($headerFields);
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

    private function canonicalizedFields(?Dictionary $headerFields): Dictionary
    {
        if (!$headerFields instanceof Dictionary) {
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
    }

    private function expectedContentLength(?Dictionary $headerFields): int
    {
        if ($value = $headerFields?->valueForCaseInsensitiveKey("Content-Length")) {
            return new Number($value)->intValue;
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
     * @return string|null The value associated with the given header field, or null if no value is associated with the field.
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
            HTTPStatusCode::continue => localized_string("Continue"),
            HTTPStatusCode::switchingProtocols => localized_string("Switching protocols"),
            HTTPStatusCode::ok => localized_string("OK"),
            HTTPStatusCode::created => localized_string("Created"),
            HTTPStatusCode::accepted => localized_string("Accepted"),
            HTTPStatusCode::nonAuthoritativeInformation => localized_string("Non authoritative information"),
            HTTPStatusCode::noContent => localized_string("No content"),
            HTTPStatusCode::resetContent => localized_string("Reset content"),
            HTTPStatusCode::partialContent => localized_string("Partial content"),
            HTTPStatusCode::multipleChoices => localized_string("Multiple choices"),
            HTTPStatusCode::movedPermanently => localized_string("Moved permanently"),
            HTTPStatusCode::found => localized_string("Found"),
            HTTPStatusCode::seeOther => localized_string("See other"),
            HTTPStatusCode::notModified => localized_string("Not modified"),
            HTTPStatusCode::temporaryRedirect => localized_string("Temporary redirect"),
            HTTPStatusCode::badRequest => localized_string("Bad request"),
            HTTPStatusCode::unauthorized => localized_string("Unauthorized"),
            HTTPStatusCode::paymentRequired => localized_string("Payment required"),
            HTTPStatusCode::forbidden => localized_string("Forbidden"),
            HTTPStatusCode::notFound => localized_string("Not found"),
            HTTPStatusCode::methodNotAllowed => localized_string("Method not allowed"),
            HTTPStatusCode::unacceptable => localized_string("Unacceptable"),
            HTTPStatusCode::proxyAuthenticationRequired => localized_string("Proxy authentication required"),
            HTTPStatusCode::requestTimeout => localized_string("Request time out"),
            HTTPStatusCode::conflict => localized_string("Conflict"),
            HTTPStatusCode::lengthRequired => localized_string("Length required"),
            HTTPStatusCode::preconditionFailed => localized_string("Precondition failed"),
            HTTPStatusCode::requestTooLarge => localized_string("Request too large"),
            HTTPStatusCode::requestedURLTooLong => localized_string("Requested URL too long"),
            HTTPStatusCode::unsupportedMediaType => localized_string("Unsupported media type"),
            HTTPStatusCode::requestedRangeNotSatisfiable => localized_string("Requested range not satisfiable"),
            HTTPStatusCode::expectationFailed => localized_string("Expectation failed"),
            HTTPStatusCode::internalServerError => localized_string("Internal server error"),
            HTTPStatusCode::unimplemented => localized_string("Not implemented"),
            HTTPStatusCode::badGateway => localized_string("Bad gateway"),
            HTTPStatusCode::serviceUnavailable => localized_string("Service unavailable"),
            HTTPStatusCode::gatewayTimeout => localized_string("Gateway time out"),
            HTTPStatusCode::unsupportedVersion => localized_string("Unsupported version"),
            default => localized_string("Server Error")
        };
    }
}
