<?php

declare(strict_types=1);

namespace Sabatier\Foundation;

use Override;

/**
 * A single name-value pair from the query portion of a URL.
 */
final class URLQueryItem extends ObjectClass
{
    #[Override]
    public string $description {
        get => sprintf("%s=%s", $this->name, human_readable_value($this->value));
    }

    public function __construct(public string $name, public ?string $value = null)
    {
    }
}
