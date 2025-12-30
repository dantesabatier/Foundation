<?php

namespace Sabatier\Foundation\Networking;

use Closure;

/** @internal */
final readonly class ParsedResponseHeader
{
    private function __construct(public ParsedResponseHeaderRawVale $rawVale, public ResponseHeaderLines $lines)
    {
    }

    public static function partial(ResponseHeaderLines $lines = new ResponseHeaderLines()): ParsedResponseHeader
    {
        return new ParsedResponseHeader(ParsedResponseHeaderRawVale::partial, $lines);
    }

    public static function complete(ResponseHeaderLines $lines): ParsedResponseHeader
    {
        return new ParsedResponseHeader(ParsedResponseHeaderRawVale::complete, $lines);
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
                ParsedResponseHeaderRawVale::partial => ParsedResponseHeader::complete($this->lines),
                ParsedResponseHeaderRawVale::complete => ParsedResponseHeader::partial()
            };
        }
        $lines = match ($this->rawVale) {
            ParsedResponseHeaderRawVale::partial => $this->lines,
            ParsedResponseHeaderRawVale::complete => new ResponseHeaderLines()
        };
        return ParsedResponseHeader::partial($lines->byAppending($line));
    }
}
