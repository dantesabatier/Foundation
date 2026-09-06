<?php

declare(strict_types=1);

namespace Sabatier\Foundation\Tests;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Sabatier\Foundation\Networking\PercentDecoder;
use Sabatier\Foundation\Networking\PercentDecoderElementRawValue;

final class PercentDecoderTest extends TestCase
{
    public function testMixedTextAndEncodedBytes(): void
    {
        $elements = iterator_to_array(new PercentDecoder("a+%20%C3%b1%00z"), false);
        $this->assertSame(["a", "+", null, null, null, null, "z"], array_column($elements, "character"));
        $this->assertSame([null, null, "%20", "%C3", "%b1", "%00", null], array_column($elements, "byte"));
        $this->assertSame(PercentDecoderElementRawValue::asciiCharacter, $elements[0]->rawValue);
        $this->assertSame(PercentDecoderElementRawValue::decodedByte, $elements[2]->rawValue);
    }

    #[DataProvider("invalidSequences")]
    public function testInvalidSequences(string $input): void
    {
        $elements = iterator_to_array(new PercentDecoder($input), false);
        $this->assertCount(1, $elements);
        $this->assertSame(PercentDecoderElementRawValue::invalid, $elements[0]->rawValue);
    }

    public static function invalidSequences(): array
    {
        return [["%"], ["%A"], ["%GG"], ["%0G"], ["%G0"]];
    }

    public function testEmptyInput(): void
    {
        $this->assertSame([], iterator_to_array(new PercentDecoder("")));
    }

    public function testCurrentDoesNotAdvanceAndRewindRestartsIteration(): void
    {
        $decoder = new PercentDecoder("%41b");
        $this->assertSame("%41", $decoder->current()->byte);
        $this->assertSame("%41", $decoder->current()->byte);
        $this->assertSame(0, $decoder->key());
        $decoder->next();
        $this->assertSame("b", $decoder->current()->character);
        $this->assertSame(3, $decoder->key());
        $decoder->rewind();
        $this->assertSame("%41", $decoder->current()->byte);
    }
}
