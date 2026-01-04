<?php

namespace Sabatier\Foundation;

use ArrayAccess;
use Iterator;

/**
 * @phpstan-require-implements ArrayAccess
 * @phpstan-require-implements Iterator
 */
trait IteratorAlgorithms
{
    private int $position = 0;
    private bool $iterating = false;

    public function current(): mixed
    {
        return $this->offsetGet($this->position);
    }

    public function next(): void
    {
        $this->position += 1;
    }

    public function key(): int
    {
        return $this->position;
    }

    public function valid(): bool
    {
        return $this->iterating = $this->offsetExists($this->position);
    }

    public function rewind(): void
    {
        $this->iterating = true;
        $this->position = 0;
    }

    protected function assertNotIterating(): void
    {
        if ($this->iterating) {
            throw new GenericException("Collection $this->debugDescription was mutated while being enumerate");
        }
    }
}
