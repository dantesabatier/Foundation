<?php

namespace Sabatier\Foundation;

/**
 * Class PropertyListSerializationMutabilityOptions
 * These constants specify mutability options in property lists.
 * @package Sabatier\Foundation
 */
class PropertyListSerializationMutabilityOptions
{
    /** @var int Causes the returned property list to have mutable containers but immutable leaves. */
    final const mutableContainers = 1;
    /** @var int Causes the returned property list to have mutable containers and leaves. */
    final const mutableContainersAndLeaves = 3;
}
