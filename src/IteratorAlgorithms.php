<?php

declare(strict_types=1);

namespace Sabatier\Foundation;

use ArrayAccess;
use Iterator;

/**
 * @psalm-require-implements ArrayAccess
 * @psalm-require-implements Iterator
 */
trait IteratorAlgorithms
{
    private int $position = 0;

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
        return $this->offsetExists($this->position);
    }

    public function rewind(): void
    {
        $this->position = 0;
    }
}
