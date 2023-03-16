<?php

namespace Sabatier\Foundation\Networking;

/** @internal */
readonly class PercentDecoderElement
{
    private function __construct(public PercentDecoderElementRawValue $rawValue, public ?string $character = null, public ?string $byte = null)
    {
    }

    public static function asciiCharacter(string $character): PercentDecoderElement
    {
        return new PercentDecoderElement(PercentDecoderElementRawValue::asciiCharacter, $character);
    }

    public static function decodedByte(string $byte): PercentDecoderElement
    {
        return new PercentDecoderElement(PercentDecoderElementRawValue::decodedByte, byte: $byte);
    }

    public static function invalid(): PercentDecoderElement
    {
        return new PercentDecoderElement(PercentDecoderElementRawValue::invalid);
    }
}
