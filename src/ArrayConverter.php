<?php

declare(strict_types=1);

namespace Sabatier\Foundation;

/**
 * @internal
 */
final class ArrayConverter
{
    private(set) ArrayClass $array {
        get => $this->array ??= SequentialArrayStrategy::instance()->convert($this->reserved, $this->preserveNull);
    }
    private(set) Dictionary $dictionary {
        get => $this->dictionary ??= AssociativeArrayStrategy::instance()->convert(is_sequential($this->reserved) ? array_combine(array_map(human_readable_value(...), array_keys($this->reserved)), array_values($this->reserved)) : $this->reserved, $this->preserveNull);
    }

    /**
     * @param array<array-key, mixed> $reserved
     */
    public function __construct(private readonly array $reserved, private readonly bool $preserveNull = true)
    {
    }
}
