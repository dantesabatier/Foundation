<?php

declare(strict_types=1);

namespace Sabatier\Foundation;

/** @internal */
interface ArrayConversionStrategy
{
    public ArrayConversionStrategy $nestedConversionStrategy {
        get;
    }

    public function convert(array $array, bool $preserveNull = true): mixed;
}
