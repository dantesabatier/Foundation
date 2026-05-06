<?php

namespace Sabatier\Foundation;

/**
 * @internal
 */
final class ArrayConverter
{
    private SequentialArrayStrategy $sequentialArrayStrategy {
        get => $this->sequentialArrayStrategy ??= new SequentialArrayStrategy();
    }
    private AssociativeArrayStrategy $associativeArrayStrategy {
        get => $this->associativeArrayStrategy ??= new AssociativeArrayStrategy();
    }
    private(set) ArrayClass $array {
        get => $this->array ??= $this->sequentialArrayStrategy->convert($this->reserved);
    }
    private(set) Dictionary $dictionary {
        get => $this->dictionary ??= $this->associativeArrayStrategy->convert(is_sequential($this->reserved) ? array_combine(array_map(human_readable_value(...), array_keys($this->reserved)), array_values($this->reserved)) : $this->reserved);
    }

    /**
     * @param array<array-key, mixed> $reserved
     */
    public function __construct(private readonly array $reserved)
    {
    }
}
