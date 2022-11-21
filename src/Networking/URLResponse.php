<?php

namespace Sabatier\Foundation\Networking;

use Sabatier\Foundation\ObjectClass;
use Sabatier\Foundation\URL;

/**
 * The metadata associated with the response to a URL load request, independent of protocol and URL scheme.
 */
class URLResponse extends ObjectClass
{
    /**
     * Creates an initialized URLResponse object with the URL, MIME type, length, and text encoding set to given values.
     * @param URL $url The URL for the response.
     * @param string|null $mimeType The MIME type of the response.
     * @param int $expectedContentLength The expected length of the response's content.
     * @param string|null $textEncodingName The name of the text encoding provided by the response's originating source.
     * @param string|null $suggestedFilename A suggested filename for the response data.
     */
    public function __construct(public readonly URL $url, public readonly ?string $mimeType, public readonly int $expectedContentLength, public readonly ?string $textEncodingName, public readonly ?string $suggestedFilename)
    {
    }
}
