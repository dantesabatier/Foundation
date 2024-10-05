<?php

namespace Sabatier\Foundation;

use Closure;

/**
 * @psalm-require-implements BidirectionalCollection
 */
trait BidirectionalCollectionAlgorithms
{
    use CollectionAlgorithms;

    public function indexBefore(int $i): int
    {
        return $i - 1;
    }

    public function formIndexBefore(int &$i): void
    {
        $i -= 1;
    }

    /** @noinspection PhpMixedReturnTypeCanBeReducedInspection */
    public function lastIndex(Closure $where): mixed
    {
        $start = $this->startIndex();
        $i = $this->endIndex();
        while ($i !== $start) {
            $this->formIndexBefore($i);
            if ($where($this[$i])) {
                return $i;
            }
        }
        return null;
    }

    public function last(Closure $where = null): mixed
    {
        if ($this->isEmpty) {
            return null;
        }
        if ($where === null) {
            return $this[$this->indexBefore($this->endIndex())];
        }
        $i = $this->lastIndex($where);
        if ($i !== null) {
            return $this[$i];
        }
        return null;
    }

    public function randomElement(RandomNumberGenerator $generator = new SystemRandomNumberGenerator()): mixed
    {
        if ($this->isEmpty) {
            return null;
        }
        $i = $generator->next($this->indexBefore($this->endIndex()));
        if ($this instanceof Dictionary) {
            return $this[$this->keys[$i]];
        }
        return $this[$i];
    }

    public function reverse(): self
    {
        $this->reserved = array_reverse($this->reserved);
        return $this;
    }

    public function reversed(): self
    {
        $instance = clone $this;
        $instance->reverse();
        return $instance;
    }
}
