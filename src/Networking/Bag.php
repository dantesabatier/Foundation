<?php

namespace Sabatier\Foundation\Networking;

use Sabatier\Foundation\ArrayClass;

/**
 * @template Element
 * @internal
 */
readonly class Bag
{
    /** @var ArrayClass<Element> */
    public ArrayClass $values;

    public function __construct()
    {
        $this->values = new ArrayClass();
    }
}
