<?php

declare(strict_types=1);

namespace Sabatier\Foundation\Networking;

use Sabatier\Foundation\ArrayClass;
use Sabatier\Foundation\Dictionary;
use Sabatier\Foundation\URL;

/** @internal */
final readonly class ResponseHeaderLines
{
    /** @param ArrayClass<string> $lines */
    public function __construct(public ArrayClass $lines = new ArrayClass())
    {
    }

    public function createURLResponse(URL $url, int $contentLength): URLResponse
    {
        return new URLResponse($url, expectedContentLength: $contentLength);
    }

    public function createHTTPURLResponse(URL $url): ?HTTPURLResponse
    {
        if (!($components = $this->decompose())) {
            return null;
        }
        [$head, $tail] = $components;
        [$version, $statusCode] = explode(" ", (string)$head);
        return new HTTPURLResponse($url, (int)$statusCode, $version, $tail->reduce(new Dictionary(), function (Dictionary $headerFields, string $header): Dictionary {
            $components = explode(":", $header, 2);
            if (count($components) === 2) {
                [$key, $value] = $components;
                $headerFields[trim($key)] = trim($value);
            }
            return $headerFields;
        }));
    }

    public function byAppending(string $line): ResponseHeaderLines
    {
        $this->lines->append($line);
        return new ResponseHeaderLines($this->lines);
    }

    private function decompose(): ?array
    {
        if (!($head = $this->lines->popFirst())) {
            return null;
        }
        return [$head, $this->lines];
    }
}
