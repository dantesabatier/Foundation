<?php

/**
 * @author Dante Sabatier <dantesabatier@me.com>
 * @version 1.0
 * @package Sabatier\Foundation
 */

namespace Sabatier\Foundation;

use JetBrains\PhpStorm\ExpectedValues;

/**
 * Class URLRequest
 * A URL load request that is independent of protocol or URL scheme.
 * @package Sabatier\Foundation
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
    /** @var URL|null The main document URL associated with this request. This URL is used for the cookie “same domain as main document” policy. */
    public ?URL $mainDocumentURL = null;
    /** @var float The timeout interval of the request. */
    public float $timeoutInterval = 60.0;
    /** @var bool A Boolean value indicating whether cookies will be sent with and set for this request. */
    public bool $httpShouldHandleCookies = true;
    /** @var bool A Boolean value indicating whether the request should transmit before the previous response is received. */
    public bool $httpShouldUsePipelining = false;
    /** @var URLRequestAttribution The entity that initiates the network request. */
    public URLRequestAttribution $attribution = URLRequestAttribution::developer;

    /**
     * Creates and initializes a URL request with the given URL.
     * @param URL $url The URL for the request.
     */
    public function __construct(public URL $url)
    {
        unset($this->httpBody);
    }

    public function __get(string $name)
    {
        if ($name == 'httpBody') {
            $httpBody = null;
            if ($this->httpMethod !== HTTPRequestMethod::get && $this->httpMethod !== HTTPRequestMethod::head && $this->httpMethod !== HTTPRequestMethod::options) {
                $contentType = $this->valueForHttpHeaderField("Content-Type") ?? "text/plain";
                $mediaType = $contentType;
                if (string_contains($contentType, ";")) {
                    [$mediaType,] = explode(";", $contentType);
                }
                if (string_is_equal($mediaType, "application/x-www-form-urlencoded", CompareOptions::caseInsensitive)) {
                    parse_str(urldecode(file_get_contents("php://input")), $body);
                    if (!empty($body)) {
                        $httpBody = json_encode($body);
                    }
                } elseif (string_has_prefix($mediaType, "multipart/form-data", CompareOptions::caseInsensitive)) {
                    $httpBody = json_encode(empty($_FILES) ? $_POST : $_FILES);
                } else {
                    $httpBody = file_get_contents('php://input');
                }
            }
            $this->$name = $httpBody;
            return $this->$name;
        } else {
            return $this->valueForUndefinedKey($name);
        }
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
        if ($this->allHTTPHeaderFields === null) {
            $this->allHTTPHeaderFields = new Dictionary();
        }
        $this->allHTTPHeaderFields->setValueForCaseInsensitiveKey($value, $field);
    }

    /**
     * @param string $field The header field name to use for the lookup (case-insensitive).
     * @return string|null The value associated with the header field, or nil if there is no corresponding header field.
     */
    public function valueForHttpHeaderField(string $field): ?string
    {
        return $this->allHTTPHeaderFields?->valueForCaseInsensitiveKey($field);
    }
}
