<?php

/**
 * Created by PhpStorm.
 * User: dante
 * Date: 03/07/20
 * Time: 13:28
 */

namespace Sabatier\Foundation;

readonly class KeyValueObservation
{
    public function __construct(public KeyValueObserving $object, public string $keyPath)
    {
    }

    public function invalidate(): void
    {
        $this->object->removeObserver($this, $this->keyPath);
    }
}
