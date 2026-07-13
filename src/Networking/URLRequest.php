<?php

declare(strict_types=1);

namespace Sabatier\Foundation\Networking;

use JetBrains\PhpStorm\ExpectedValues;
use Override;
use Sabatier\Foundation\Dictionary;
use Sabatier\Foundation\ObjectClass;
use Sabatier\Foundation\URL;

/**
 * A URL load request that is independent of protocol or URL scheme.
 */
class URLRequest extends ObjectClass
{
    /** @var string The HTTP request method. */
    #[ExpectedValues(valuesFromClass: HTTPRequestMethod::class)]
    public string $httpMethod = HTTPRequestMethod::get;
    /** @var Dictionary<string>|null A dictionary containing all the HTTP header fields for a request. */
    public ?Dictionary $allHTTPHeaderFields = null;
    /** @var string|null The data sent as the message body of a request, such as for an HTTP POST request. */
    public ?string $httpBody = null;
    /** @var resource|null The stream used to deliver the HTTP body. */
    public mixed $httpBodyStream = null;
    /** @var URL|null The main document URL associated with this request. This URL is used for the cookie "same domain as main document" policy. */
    public ?URL $mainDocumentURL = null;
    /** @var bool A Boolean value indicating whether cookies will be sent with and set for this request. */
    public bool $httpShouldHandleCookies = true;
    /** @var bool A Boolean value indicating whether the request should transmit before the previous response is received. */
    public bool $httpShouldUsePipelining = false;
    /** @var URLRequestAttribution The entity that initiates the network request. */
    public URLRequestAttribution $attribution = URLRequestAttribution::developer;
    /** @internal */
    public Dictionary $protocolProperties;
    #[Override]
    public string $description {
        get => "<URLRequest $this->hash> { URL: $this->url }";
    }

    /**
     * Creates and initializes a URL request with the given URL.
     * @param URL $url The URL for the request.
     * @param URLRequestCachePolicy $cachePolicy The cache policy for the request.
     * @param float $timeoutInterval The timeout interval for the request.
     */
    public function __construct(public URL $url, public URLRequestCachePolicy $cachePolicy = URLRequestCachePolicy::useProtocolCachePolicy, public float $timeoutInterval = 60.0)
    {
        $this->protocolProperties = new Dictionary();
    }

    /**
     * @return array<string, mixed>
     */
    public function getParsedBody(): array
    {
        $contentType = $this->valueForHttpHeaderField("Content-Type") ?? "text/plain";
        $mediaType = $contentType;
        if (str_contains($contentType, ";")) {
            [$mediaType,] = explode(";", $contentType);
        }
        return match ($mediaType) {
            "application/x-www-form-urlencoded" => (function (): array {
                parse_str((string)$this->httpBody, $result);
                return $result;
            })(),
            "multipart/form-data" => $_POST,
            "application/json" => json_decode($this->httpBody ?? "[]", true) ?? [],
            default => []
        };
    }

    /**
     * Adds a value to the header field.
     * This method provides the ability to add values to header fields incrementally. If a value was previously set for the specified field, the supplied value is appended to the existing value using the appropriate field delimiter (a comma).
     * @param string $value The value for the header field.
     * @param string $field The name of the header field. In keeping with the HTTP RFC, HTTP header field names are case-insensitive.
     */
    public function addValueForHttpHeaderField(string $value, string $field): void
    {
        if ($currentValue = $this->valueForHttpHeaderField($field)) {
            $value = "$currentValue, $value";
        }
        $this->setValueForHttpHeaderField($value, $field);
    }

    /**
     * Sets a value for the header field.
     * @param string|null $value The new value for the header field. Any existing value for the field is replaced by the new value.
     * @param string $field The name of the header field to set. In keeping with the HTTP RFC, HTTP header field names are case-insensitive.
     */
    public function setValueForHttpHeaderField(?string $value, string $field): void
    {
        $this->allHTTPHeaderFields ??= new Dictionary();
        /** @psalm-suppress InvalidArgument */
        $this->allHTTPHeaderFields[$field] = $value;
    }

    /**
     * @param string $field The header field name to use for the lookup (case-insensitive).
     * @return string|null The value associated with the header field, or null if there is no corresponding header field.
     */
    public function valueForHttpHeaderField(string $field): ?string
    {
        return $this->allHTTPHeaderFields?->valueForCaseInsensitiveKey($field);
    }
}
