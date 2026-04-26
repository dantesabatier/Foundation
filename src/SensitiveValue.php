<?php

declare(strict_types=1);

namespace Sabatier\Foundation;

use JsonSerializable;
use Override;

final class SensitiveValue implements CustomStringConvertible, JsonSerializable
{
    public string $description {
        get => sprintf("%s(%s)", typeof($this->value), class_name(self::class));
    }

    public function __construct(private readonly mixed $value)
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
