<?php

declare(strict_types=1);

namespace Sabatier\Foundation;

/** @internal */
interface ArrayConversionStrategy
{
    public function convert(array $array): mixed;
}
