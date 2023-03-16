<?php

namespace Sabatier\Foundation\Networking;

/** @internal */
enum PercentDecoderElementRawValue: int
{
    case asciiCharacter = 0;
    case decodedByte = 1;
    case invalid = 2;
}
