<?php

namespace Sabatier\Foundation\Networking;

/** @internal */
readonly class EasyHandleWriteBufferResult
{
    private function __construct(public EasyHandleWriteBufferResultRawValue $rawValue, public string $bytes = "")
    {
    }

    public static function abort(): EasyHandleWriteBufferResult
    {
        return new EasyHandleWriteBufferResult(EasyHandleWriteBufferResultRawValue::abort);
    }

    public static function pause(): EasyHandleWriteBufferResult
    {
        return new EasyHandleWriteBufferResult(EasyHandleWriteBufferResultRawValue::pause);
    }

    public static function bytes(string $bytes): EasyHandleWriteBufferResult
    {
        return new EasyHandleWriteBufferResult(EasyHandleWriteBufferResultRawValue::bytes, $bytes);
    }
}
