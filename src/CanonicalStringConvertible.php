<?php

declare(strict_types=1);

namespace Sabatier\Foundation;

/**
 * A deterministic, canonical string representation of an object.
 */
interface CanonicalStringConvertible
{
    public string $canonicalDescription {
        get;
    }
}
