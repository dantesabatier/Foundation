<?php

namespace Sabatier\Foundation;

/**
 * @internal
 */
final class ArrayConverter
{
    private(set) ArrayClass $array {
        get => $this->array ??= new SequentialArrayStrategy()->convert($this->reserved);
    }
    private(set) Dictionary $dictionary {
        get => $this->dictionary ??= new AssociativeArrayStrategy()->convert($this->reserved !== [] && is_sequential($this->reserved) ? array_combine(array_map(human_readable_value(...), array_keys($this->reserved)), array_values($this->reserved)) : $this->reserved);
    }

    /**
     * @param array<array-key, mixed> $reserved
     */
    public function __construct(private readonly array $reserved)
    {
    }
}
