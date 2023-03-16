<?php

namespace Sabatier\Foundation\Networking;

use Closure;

/** @internal */
readonly class ParsedResponseHeader
{
    private function __construct(public ParsedResponseHeaderRawVale $rawVale, public ResponseHeaderLines $header)
    {
    }

    public static function partial(ResponseHeaderLines $header = new ResponseHeaderLines()): ParsedResponseHeader
    {
        return new ParsedResponseHeader(ParsedResponseHeaderRawVale::partial, $header);
    }

    public static function complete(ResponseHeaderLines $header): ParsedResponseHeader
    {
        return new ParsedResponseHeader(ParsedResponseHeaderRawVale::complete, $header);
    }

    public function byAppending(string $data, Closure $onHeaderCompleted): ?ParsedResponseHeader
    {
        $l = strlen($data);
        if ($l < 2 || $data[$l - 2] !== "\r" || $data[$l - 1] !== "\n") {
            return null;
        }
        $line = substr($data, 0, $l - 2);
        return $this->appending($line, $onHeaderCompleted);
    }

    private function appending(string $line, Closure $onHeaderCompleted): ParsedResponseHeader
    {
        if ($onHeaderCompleted($line)) {
            return match ($this->rawVale) {
                ParsedResponseHeaderRawVale::partial => ParsedResponseHeader::complete($this->header),
                ParsedResponseHeaderRawVale::complete => ParsedResponseHeader::partial()
            };
        } else {
            $header = match ($this->rawVale) {
                ParsedResponseHeaderRawVale::partial => $this->header,
                ParsedResponseHeaderRawVale::complete => new ResponseHeaderLines()
            };
            return ParsedResponseHeader::partial($header->byAppending($line));
        }
    }
}
