<?php

namespace Sabatier\Foundation\Networking;

use Iterator;
use Override;
use function Sabatier\Foundation\in_range;

/**
 * @template-implements Iterator<PercentDecoderElement>
 * @internal
 */
class PercentDecoder implements Iterator
{
    private int $index = 0;

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
        $this->next();
        if (!$this->valid()) {
            return PercentDecoderElement::invalid();
        }
        $h = $this->string[$this->index];
        $this->next();
        if (!$this->valid()) {
            return PercentDecoderElement::invalid();
        }
        $l = $this->string[$this->index];
        return PercentDecoderElement::decodedByte("$c$h$l");
    }

    #[Override]
    public function next(): void
    {
        $this->index += 1;
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
