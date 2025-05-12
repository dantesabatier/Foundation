<?php

namespace Sabatier\Foundation;

/** @internal */
class SequentialArrayStrategy implements ArrayConversionStrategy
{
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
