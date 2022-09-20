<?php

namespace Sabatier\Foundation;

/**
 * @psalm-require-implements RangeReplaceableCollection
 */
trait RangeReplaceableCollectionAlgorithms
{
    use MutableCollectionAlgorithms;

    public static function repeating(mixed $value, int $count): self
    {
        assert($count >= 0, "invalid argument: count must be zero or greater");
        return new self(array_fill(0, $count, $value));
    }
}