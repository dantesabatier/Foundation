<?php

namespace Sabatier\Foundation;

/** @internal */
readonly class KeyPathComponents
{
    public function __construct(public string $key = "", public ?string $remainderPath = null)
    {
    }
}
