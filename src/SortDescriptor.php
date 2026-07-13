<?php

declare(strict_types=1);

namespace Sabatier\Foundation;

use Closure;
use Override;

/**
 * An immutable description of how to order a collection of objects based on a property common to all the objects.
 */
final class SortDescriptor extends ObjectClass
{
    /** @var SortDescriptor Returns a sort descriptor that reverses the sort order. */
    public SortDescriptor $reversedSortDescriptor {
        get => $this->reversedSortDescriptor ??= new SortDescriptor($this->key, !$this->ascending, $this->comparator);
    }
    #[Override]
    public string $description {
        get => sprintf("%s %s", $this->key, human_readable_value($this->ascending));
    }
    #[Override]
    public string $canonicalDescription {
        get => sprintf("%s:%s:%s", $this->key, $this->ascending ? "asc" : "desc", $this->comparator ? "cmp" : "nocmp");
    }

    /**
     * Initializes a sort descriptor with a given key path and ordering, and a comparator block.
     * @param string $key The key that specifies the property to be compared during sorting.
     * @param bool $ascending A Boolean value that indicates whether the receiver specifies sorting in ascending order.
     * @param Closure(mixed, mixed): ComparisonResult|null $comparator The comparator for the sort descriptor.
     */
    public function __construct(public readonly string $key, public readonly bool $ascending = true, public readonly ?Closure $comparator = null)
    {
    }

    /**
     * Returns a comparison result value that indicates the sort order of two objects.
     * @param mixed $object1 The object to compare with object2. This object must have a property accessible using the key-path specified by {@see key}.
     * @param mixed $object2 The object to compare with object1. This object must have a property accessible using the key-path specified by {@see key}.
     * @return ComparisonResult orderedAscending if object1 is less than object2, orderedDescending if object1 is greater than object2, or orderedSame if object1 is equal to object2.
     */
    public function compareObject(mixed $object1, mixed $object2): ComparisonResult
    {
        $comparator = $this->comparator;
        if ($comparator) {
            return $comparator($object1, $object2);
        }
        $object1 instanceof KeyValueCoding && $object2 instanceof KeyValueCoding ?: [$object1, $object2]
                |> human_readable_value(...)
                |> (fn(string $x): string => sprintf("Invalid arguments, sort descriptors are meant to be used with %s objects exclusively, %s given", KeyValueCoding::class, $x))
                |> fatal_error(...);
        return ComparisonResult::from(($this->ascending ? ComparisonResult::orderedAscending->value : ComparisonResult::orderedDescending->value) * ($object1->valueForKeyPath($this->key) <=> $object2->valueForKeyPath($this->key)));
    }

    /**
     * Forces a securely decoded sort descriptor to allow evaluation.
     */
    public function allowEvaluation(): void
    {
    }

    #[Override]
    public function isEqual(mixed $other): bool
    {
        if ($other instanceof SortDescriptor) {
            return $this->key === $other->key && $this->ascending === $other->ascending;
        }
        return false;
    }
}
