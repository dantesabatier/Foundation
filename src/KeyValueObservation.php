<?php
/**
 * Created by PhpStorm.
 * User: dante
 * Date: 03/07/20
 * Time: 13:28
 */

namespace Sabatier\Foundation;

class KeyValueObservation
{
    public function __construct(public readonly KeyValueObserving $object, public readonly string $keyPath)
    {
    }

    public function invalidate(): void
    {
        $this->object->removeObserver($this, $this->keyPath);
    }
}
