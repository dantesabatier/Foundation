<?php

namespace Sabatier\Foundation;

final class CollectionDifferenceChange extends ObjectClass
{
    public function __construct(readonly public CollectionDifferenceChangeType $type, public readonly mixed $element, public readonly int $offset, public ?int $targetOffset = null)
    {
    }

    public string $description {
        get => sprintf("CollectionDifferenceChange %s(offset: %d, element: %s, associatedWith: %s)", $this->type->name, $this->offset, human_readable_value($this->element), human_readable_value($this->targetOffset));
    }
}
