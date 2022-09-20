<?php

namespace Sabatier\Foundation;

/**
 * Class URLQueryItem
 * A single name-value pair from the query portion of a URL.
 * @package Sabatier\Foundation
 */
class URLQueryItem
{
    public function __construct(public string $name, public ?string $value = null)
    {
    }

    public function __toString(): string
    {
        return $this->description();
    }

    public function description(): string
    {
        return sprintf("%s=%s", $this->name, human_readable_value($this->value));
    }
}
