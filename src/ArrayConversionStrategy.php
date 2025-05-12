<?php

namespace Sabatier\Foundation;

/** @internal */
interface ArrayConversionStrategy
{
    public function convert(array $array): mixed;
}
