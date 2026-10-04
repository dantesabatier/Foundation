<?php

declare(strict_types=1);

namespace Sabatier\Foundation;

/**
 * A type whose conceptual equality can be summarized in a hash value, so a collection can find an equal instance without comparing it against every element.
 *
 * The hash value is unrelated to {@see ObjectProtocol::$hash}, which stays the instance identity: two equal instances keep distinct identities and share a hash value. Adopt it only when all of these hold:
 *  - instances that are equal through {@see Equatable::isEqual()} have the same hash value; unequal instances may share one, at the cost of a comparison;
 *  - an instance is only ever equal to instances of its own kind, never to a scalar or to a value of another type;
 *  - equality is an equivalence: if a equals b and b equals c, a equals c;
 *  - the hash value never changes while the instance is held by a collection, which in practice means the type is immutable.
 *
 * A type that cannot promise all of them simply does not adopt the protocol, and the collections keep comparing it element by element.
 */
interface Hashable extends Equatable
{
    /** @var string A value that equal instances share. */
    public string $hashValue {
        get;
    }
}
