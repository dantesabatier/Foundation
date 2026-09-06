<?php

declare(strict_types=1);

namespace Sabatier\Foundation\Networking;

use Iterator;
use Override;
use function Sabatier\Foundation\in_range;

/**
 * @template-implements Iterator<PercentDecoderElement>
 * @internal
 */
final class PercentDecoder implements Iterator
{
    private int $index = 0;

    /** @param string $string */
    public function __construct(private readonly string $string)
    {
    }

    #[Override]
    public function current(): PercentDecoderElement
    {
        $c = $this->string[$this->index];
        if ($c !== "%") {
            return PercentDecoderElement::asciiCharacter($c);
        }
        if ($this->index + 2 >= strlen($this->string)) {
            return PercentDecoderElement::invalid();
        }
        $h = $this->string[$this->index + 1];
        $l = $this->string[$this->index + 2];
        if (!ctype_xdigit($h . $l)) {
            return PercentDecoderElement::invalid();
        }
        return PercentDecoderElement::decodedByte("$c$h$l");
    }

    #[Override]
    public function next(): void
    {
        $this->index += ($this->string[$this->index] ?? null) === "%" ? 3 : 1;
    }

    #[Override]
    public function key(): int
    {
        return $this->index;
    }

    #[Override]
    public function valid(): bool
    {
        return in_range($this->index, 0, strlen($this->string));
    }

    #[Override]
    public function rewind(): void
    {
        $this->index = 0;
    }
}
