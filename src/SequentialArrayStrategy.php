<?php

namespace Sabatier\Foundation;

use Override;

/** @internal */
final class SequentialArrayStrategy implements ArrayConversionStrategy
{
    #[Override]
    public function convert(array $array): ArrayClass
    {
        /** @var ArrayClass<mixed> $arrayClass */
        $arrayClass = new ArrayClass();
        foreach ($array as $i => $value) {
            if (is_array($value)) {
                $arrayClass[$i] = is_sequential($value) ? $this->convert($value) : new AssociativeArrayStrategy()->convert($value);
            } else {
                $arrayClass[$i] = $value ?? Nil::nil();
            }
        }
        return $arrayClass;
    }
}
