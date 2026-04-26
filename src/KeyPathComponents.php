<?php

declare(strict_types=1);

namespace Sabatier\Foundation;

/** @internal */
final readonly class KeyPathComponents
{
    public function __construct(public string $key, public ?string $remainderPath = null)
    {
    }
}
