<?php

declare(strict_types=1);

namespace Sabatier\Foundation;

use stdClass;

/**
 * @internal
 */
final class ArrayConverter
{
    private(set) ArrayClass $array {
        get => $this->array ??= SequentialArrayStrategy::instance()->convert($this->reserved, $this->preserveNull);
    }
    private(set) Dictionary $dictionary {
        get => $this->dictionary ??= AssociativeArrayStrategy::instance()->convert(is_array($this->reserved) && is_sequential($this->reserved) ? array_combine(array_map(human_readable_value(...), array_keys($this->reserved)), array_values($this->reserved)) : $this->reserved, $this->preserveNull);
    }

    /**
     * @param array<array-key, mixed>|stdClass $reserved The decoded value to convert; a `stdClass` keeps the `{}` it came from, which an array cannot express once empty.
     * @param bool $preserveNull Whether a `null` element is preserved as `Nil` rather than dropped.
     */
    public function __construct(private readonly array|stdClass $reserved, private readonly bool $preserveNull = true)
    {
    }
}
