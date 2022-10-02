<?php

/**
 * Created by PhpStorm.
 * User: dante
 * Date: 28/06/20
 * Time: 18:18
 */

namespace Sabatier\Foundation;

use Closure;

/** @internal */
class KeyValueObservance
{
    public function __construct(public readonly object $observer, public readonly string $keyPath, public readonly int $options, public readonly mixed $context = null, public readonly ?Closure $handler = null)
    {
    }
}
