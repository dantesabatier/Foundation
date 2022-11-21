<?php

namespace Sabatier\Foundation;

use Closure;

/**
 * An immutable description of how to order a collection of objects based on a property common to all the objects.
 */
class SortDescriptor extends ObjectClass
{
    /** @var SortDescriptor Returns a sort descriptor that reverses the sort order. */
    public readonly SortDescriptor $reversedSortDescriptor;

    /**
     * Initializes a sort descriptor with a given key path and ordering, and a comparator block.
     * @param string $key The key that specifies the property to be compared during sorting.
     * @param bool $ascending A Boolean value that indicates whether the receiver specifies sorting in ascending order.
     * @param Closure(mixed, mixed): ComparisonResult|null $comparator The comparator for the sort descriptor.
     */
    public function __construct(public readonly string $key, public readonly bool $ascending = true, public readonly ?Closure $comparator = null)
    {
        unset($this->reversedSortDescriptor);
    }

    public function __get(string $name)
    {
        return $this->$name = match ($name) {
            'reversedSortDescriptor' => new SortDescriptor($this->key, !$this->ascending, $this->comparator),
            default => $this->valueForUndefinedKey($name)
        };
    }

    /**
     * Returns a comparison result value that indicates the sort order of two objects.
     * @param mixed $object1 The object to compare with object2. This object must have a property accessible using the key-path specified by {@see key}.
     * @param mixed $object2 The object to compare with object1. This object must have a property accessible using the key-path specified by {@see key}.
     * @return ComparisonResult {@see ComparisonResult::orderedAscending} if object1 is less than object2, {@see ComparisonResult::orderedDescending} if object1 is greater than object2, or {@see ComparisonResult::orderedSame} if object1 is equal to object2.
     */
    public function compareObject(mixed $object1, mixed $object2): ComparisonResult
    {
        $comparator = $this->comparator;
        if ($comparator) {
            return $comparator($object1, $object2);
        }
        assert($object1 instanceof KeyValueCoding && $object2 instanceof KeyValueCoding, sprintf("invalid arguments, sort descriptors are meant to be used with %s objects exclusively, %s given", KeyValueCoding::class, human_readable_value([$object1, $object2])));
        return ComparisonResult::from(($this->ascending ? ComparisonResult::orderedAscending->value : ComparisonResult::orderedDescending->value) * ($object1->valueForKeyPath($this->key) <=> $object2->valueForKeyPath($this->key)));
    }

    /**
     * Forces a securely decoded sort descriptor to allow evaluation.
     */
    public function allowEvaluation(): void
    {
    }

    public function description(): string
    {
        return sprintf("%s %s", $this->key, human_readable_value($this->ascending));
    }
}
