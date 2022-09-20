<?php

namespace Sabatier\Foundation;

use InvalidArgumentException;
use JetBrains\PhpStorm\ArrayShape;
use JetBrains\PhpStorm\Pure;

/**
 * Class UUID
 * A universally unique value that can be used to identify types, interfaces, and other items.
 * @package Sabatier\Foundation
 */
class UUID extends ObjectClass
{
    /** @var string Returns a string created from the UUID, such as “E621E1F8-C36C-495A-93FC-0C247A3E6E5F” */
    public readonly string $uuidString;

    /**
     * Initializes a new UUID with RFC 4122 version 4 random bytes.
     * @param string|null $uuidString The string representation of a UUID, such as E621E1F8-C36C-495A-93FC-0C247A3E6E5F.
     */
    public function __construct(?string $uuidString = null)
    {
        if ($uuidString && !uuid_validate($uuidString)) {
            throw new InvalidArgumentException(sprintf("invalid argument: expecting uuid string, \"%s\" given", $uuidString));
        }
        $this->uuidString = $uuidString ?? uuid_generate();
    }

    #[Pure]
    #[ArrayShape(['uuidString' => "string"])]
    public function __serialize(): array
    {
        return ['uuidString' => $this->uuidString];
    }

    public function __unserialize(array $data): void
    {
        $this->uuidString = $data['uuidString'];
    }

    public function compare(mixed $other): ComparisonResult
    {
        if (is_string($other)) {
            return ComparisonResult::from(uuid_compare($this->uuidString, $other));
        } elseif ($other instanceof UUID) {
            return $this->compare($other->uuidString);
        }
        return parent::compare($other);
    }

    public function isEqual(mixed $other): bool
    {
        return $this->compare($other) == ComparisonResult::orderedSame;
    }

    /**
     * A textual description of the UUID.
     * @return string
     */
    public function description(): string
    {
        return $this->uuidString;
    }

    public function debugDescription(): string
    {
        return sprintf("<%s %s %s>", static::class, self::hash(), $this->description());
    }

    public function jsonSerialize(): string
    {
        return $this->description();
    }
}