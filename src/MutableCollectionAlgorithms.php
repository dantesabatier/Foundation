<?php

namespace Sabatier\Foundation;

use Closure;

/**
 * @psalm-require-implements MutableCollection
 */
trait MutableCollectionAlgorithms
{
    use BidirectionalCollectionAlgorithms;

    public function drop(Closure $while): Slice
    {
        $start = $this->startIndex();
        $end = $this->endIndex();
        while (($start !== $end) && $while($this[$start])) {
            $this->formIndexAfter($start);
        }
        return new Slice($this, new Range($start, $end));
    }

    public function dropFirst(int $k): Slice
    {
        assert($k >= 0, "invalid argument: k must be zero or greater");
        return new Slice($this, new Range($k, $this->endIndex()));
    }

    public function dropLast(int $k): Slice
    {
        assert($k >= 0, "invalid argument: k must be zero or greater");
        return new Slice($this, new Range($this->startIndex(), max($this->endIndex() - $k, 0)));
    }
}
