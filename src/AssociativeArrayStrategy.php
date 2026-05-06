<?php

namespace Sabatier\Foundation;

use Override;

/** @internal */
final readonly class AssociativeArrayStrategy implements ArrayConversionStrategy
{
    #[Override]
    public function convert(array $array): Dictionary
    {
        $sequentialArrayStrategy = new SequentialArrayStrategy();
        /** @var Dictionary<mixed> $dictionary */
        $dictionary = new Dictionary();
        foreach ($array as $key => $value) {
            if (is_array($value)) {
                $dictionary[$key] = is_sequential($value) ? $sequentialArrayStrategy->convert($value) : $this->convert($value);
            } else {
                $dictionary[$key] = $value;
            }
        }
        return $dictionary;
    }
}
