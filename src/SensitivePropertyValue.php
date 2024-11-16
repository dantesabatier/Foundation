<?php

namespace Sabatier\Foundation;

use JsonSerializable;
use Override;

final class SensitivePropertyValue implements CustomStringConvertible, JsonSerializable
{
    public string $description {
        get => sprintf("%s(%s)", typeof($this->value), class_name(self::class));
    }

    public function __construct(public mixed $value)
    {
    }

    #[Override]
    public function __toString(): string
    {
        return $this->description;
    }

    #[Override]
    public function jsonSerialize(): string
    {
        return $this->description;
    }
}
