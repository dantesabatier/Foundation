<?php

namespace Sabatier\Foundation\Networking;

/** @internal */
class EasyHandleWriteBufferResult
{
    private function __construct(public readonly EasyHandleWriteBufferResultRawValue $rawValue, public readonly ?string $bytes = null)
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

    public static function bytes(?string $bytes): EasyHandleWriteBufferResult
    {
        return new EasyHandleWriteBufferResult(EasyHandleWriteBufferResultRawValue::bytes, $bytes);
    }
}
