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
readonly class KeyValueObservance
{
    public function __construct(public object $observer, public string $keyPath, public int $options, public mixed $context = null, public ?Closure $handler = null)
    {
    }
}
