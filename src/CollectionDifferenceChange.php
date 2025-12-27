<?php

namespace Sabatier\Foundation;

class CollectionDifferenceChange extends ObjectClass
{
    public function __construct(readonly public CollectionDifferenceChangeType $type, public readonly mixed $element, public readonly int $offset, public ?int $targetOffset = null)
    {
    }
}
