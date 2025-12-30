<?php

namespace Sabatier\Foundation\Networking;

use Sabatier\Foundation\ArrayClass;

/**
 * @template Element
 * @internal
 */
final class Bag
{
    /** @var ArrayClass<Element> */
    public ArrayClass $values {
        get => $this->values ??= new ArrayClass();
    }
}
