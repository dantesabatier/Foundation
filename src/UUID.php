<?php

namespace Sabatier\Foundation;

use Override;

/**
 * A universally unique value that can be used to identify types, interfaces, and other items.
 */
final class UUID extends ObjectClass
{
    /** @var string Returns a string created from the UUID, such as "E621E1F8-C36C-495A-93FC-0C247A3E6E5F" */
    public readonly string $uuidString;
    /** @var string A textual description of the UUID. */
    public string $description {
        get => $this->uuidString;
    }
    public string $debugDescription {
        get => sprintf("<%s %s %s>", $this->class, $this->hash, $this->description);
    }

    /**
     * Initializes a new UUID with RFC 4122 version 4 random bytes.
     * @param string|null $uuidString The string representation of a UUID, such as E621E1F8-C36C-495A-93FC-0C247A3E6E5F.
     */
    public function __construct(?string $uuidString = null)
    {
        if ($uuidString && !uuid_validate($uuidString)) {
            fatal_error("Invalid argument: expecting uuid string, \"$uuidString\" given");
        }
        $this->uuidString = $uuidString ?? uuid_generate();
    }

    public function __serialize(): array
    {
        return ["uuidString" => $this->uuidString];
    }

    public function __unserialize(array $data): void
    {
        $this->uuidString = $data["uuidString"];
    }

    #[Override]
    public function compare(mixed $other): ComparisonResult
    {
        if ($other instanceof UUID) {
            return ComparisonResult::from(uuid_compare($this->uuidString, $other->uuidString));
        }
        return ComparisonResult::orderedDescending;
    }

    #[Override]
    public function isEqual(mixed $other): bool
    {
        return $this->compare($other) === ComparisonResult::orderedSame;
    }

    #[Override]
    public function jsonSerialize(): string
    {
        return $this->description;
    }
}
