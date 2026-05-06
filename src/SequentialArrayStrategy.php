<?php

namespace Sabatier\Foundation;

use Override;

/** @internal */
final readonly class SequentialArrayStrategy implements ArrayConversionStrategy
{
    #[Override]
    public function convert(array $array): ArrayClass
    {
        $associativeArrayStrategy = new AssociativeArrayStrategy();
        /** @var ArrayClass<mixed> $arrayClass */
        $arrayClass = new ArrayClass();
        foreach ($array as $i => $value) {
            if (is_array($value)) {
                $arrayClass[$i] = is_sequential($value) ? $this->convert($value) : $associativeArrayStrategy->convert($value);
            } else {
                $arrayClass[$i] = $value;
            }
        }
        return $arrayClass;
    }
}
