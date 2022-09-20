<?php

namespace Sabatier\Foundation;

use JetBrains\PhpStorm\Immutable;

/** @internal */
#[Immutable]
class KeyPathComponents
{
    public function __construct(public string $key = '', public ?string $remainderPath = null)
    {
    }
}
