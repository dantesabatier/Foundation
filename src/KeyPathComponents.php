<?php

namespace Sabatier\Foundation;

/** @internal */
final readonly class KeyPathComponents
{
    public function __construct(public string $key = "", public ?string $remainderPath = null)
    {
    }
}
