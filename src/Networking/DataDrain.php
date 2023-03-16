<?php

namespace Sabatier\Foundation\Networking;

use Sabatier\Foundation\URL;

/** @internal */
readonly class DataDrain
{
    private function __construct(public DataDrainRawValue $rawValue = DataDrainRawValue::inMemory, public string $bodyData = "", public ?URL $fileURL = null, public mixed $fileHandle = null)
    {
    }

    public static function inMemory(string $data = ""): DataDrain
    {
        return new DataDrain(DataDrainRawValue::inMemory, $data);
    }

    public static function toFile(?URL $fileURL, mixed $fileHandle): DataDrain
    {
        return new DataDrain(DataDrainRawValue::toFile, fileURL: $fileURL, fileHandle: $fileHandle);
    }

    public static function ignore(): DataDrain
    {
        return new DataDrain(DataDrainRawValue::ignore);
    }
}
