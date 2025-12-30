<?php

namespace Sabatier\Foundation;

use Override;

/** @internal */
final class AssociativeArrayStrategy implements ArrayConversionStrategy
{
    #[Override]
    public function convert(array $array): Dictionary
    {
        /** @var Dictionary<mixed> $dictionary */
        $dictionary = new Dictionary();
        foreach ($array as $key => $value) {
            if (is_array($value)) {
                $dictionary[$key] = is_sequential($value)
                    ? new SequentialArrayStrategy()->convert($value)
                    : $this->convert($value);
            } else {
                $dictionary[$key] = $value ?? Nil::nil();
            }
        }
        return $dictionary;
    }
}
