<?php

namespace Sabatier\Foundation;

/**
 * A single name-value pair from the query portion of a URL.
 */
class URLQueryItem extends ObjectClass
{
    public string $description {
        get => sprintf("%s=%s", $this->name, human_readable_value($this->value));
    }

    public function __construct(public string $name, public ?string $value = null)
    {
    }
}
