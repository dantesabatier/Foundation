<?php

/**
 * Created by PhpStorm.
 * User: dante
 * Date: 19/07/20
 * Time: 07:38
 */

namespace Sabatier\Foundation;

use ArrayIterator;
use Closure;
use IteratorAggregate;
use Sabatier\Foundation\Predicates\Predicate;
use Traversable;

/**
 * A collection whose elements are key-value pairs.
 * @template Element
 * @implements Collection<string, Element>
 * @implements IteratorAggregate<string, Element>
 * @property-read ArrayClass<string> $keys An array containing just the keys of the dictionary.
 * @property-read ArrayClass<Element> $values An array containing just the values of the dictionary.
 */
class Dictionary extends ObjectClass implements Collection, IteratorAggregate
{
    use CollectionAlgorithms {
        contains as private sequenceContains;
        containsElement as private sequenceContainsElement;
        first as private sequenceFirst;
        min as private sequenceMin;
        max as private sequenceMax;
        reduce as private sequenceReduce;
        indexOf as private collectionIndexOf;
        filter as private collectionFilter;
        filtered as private collectionFiltered;
        sorted as private collectionSorted;
        allSatisfy as private collectionAllSatisfy;
    }

    /**
     * @param iterable<string, Element> $iterable
     */
    public function __construct(iterable $iterable = [])
    {
        if ($iterable instanceof Dictionary) {
            $this->reserved = $iterable->toArray();
        } else {
            foreach ($iterable as $key => $value) {
                $this[$key] = $value;
            }
        }
    }

    public function __get(string $name)
    {
        return match ($name) {
            "keys" => new ArrayClass(array_keys($this->reserved)),
            "values" => new ArrayClass(array_values($this->reserved)),
            default => $this->valueForUndefinedKey($name),
        };
    }

    public static function dictionaryWithArray(array $array): Dictionary
    {
        return ArrayConverter::dictionaryWithArray($array);
    }

    /**
     * Creates a new dictionary whose keys are the groupings returned by the given closure and whose values are arrays of the elements that returned each key.
     *
     * The arrays in the “values” position of the new dictionary each contain at least one element, with the elements in the same order as the source sequence.
     * The following example declares an array of names, and then creates a dictionary from that array by grouping the names by first letter:
     * <code>
     * $students = ["Kofi", "Abena", "Efua", "Kweku", "Akosua"]
     * $studentsByLetter = Dictionary::grouping(students, by: fn(string $student): string => $student[0])
     * // ["E": ["Efua"], "K": ["Kofi", "Kweku"], "A": ["Abena", "Akosua"]]
     * </code>
     * The new studentsByLetter dictionary has three entries, with students' names grouped by the keys "E", "K", and "A".
     * @param Sequence $values A sequence of values to group into a dictionary.
     * @param Closure(mixed): string $by A closure that returns a key for each element in values.
     * @return Dictionary<ArrayClass<Dictionary>>
     */
    public static function grouping(Sequence $values, Closure $by): Dictionary
    {
        /** @var Dictionary<ArrayClass<Dictionary>> $instance */
        $instance = new Dictionary();
        foreach ($values as $e) {
            $k = $by($e);
            if ($instance[$k] !== null) {
                $instance[$k][] = $e;
            } else {
                $instance[$k] = new ArrayClass([$e]);
            }
        }
        return $instance;
    }

    /**
     * Returns a Boolean value indicating whether the sequence contains an element that satisfies the given predicate.
     * @param Closure(Element, string=): bool $predicate A closure that takes an element of the sequence as its argument and returns a Boolean value that indicates whether the passed element represents a match.
     * @return bool true if the sequence contains an element that satisfies predicate; otherwise, false.
     */
    public function contains(Closure $predicate): bool
    {
        return $this->sequenceContains($predicate);
    }

    /**
     * Returns a bool value indicating whether the sequence contains an element that satisfies the given predicate.
     *
     * Available when Element conforms to {@see Equatable}.
     * @param Element $element The element to find in the sequence.
     * @return bool {@see true} if the element was found in the sequence; otherwise, {@see false}.
     */
    public function containsElement(mixed $element): bool
    {
        return $this->sequenceContainsElement($element);
    }

    /**
     * Returns the minimum element in the sequence.
     * @return Element|null The sequence's minimum element. If the sequence has no elements, returns nil.
     */
    public function min()
    {
        return $this->sequenceMin();
    }

    /**
     * Returns the maximum element in the sequence.
     * @return Element|null The sequence's maximum element. If the sequence has no elements, returns nil.
     */
    public function max()
    {
        return $this->sequenceMax();
    }

    /**
     * Returns the result of combining the elements of the sequence using the given closure.
     *
     * Use the {@see reduce()} method to produce a single value from the elements of an entire sequence.
     * For example, you can use this method on an array of integers to filter adjacent equal entries or count frequencies.
     * @template Result
     * @param Result $initialResult The value to use as the initial accumulating value.
     * @param Closure(Result, Element, string=): Result $updateAccumulatingResult A closure that updates the accumulating value with an element of the sequence.
     * @return Result The final accumulated value. If the sequence has no elements, the result is initialResult.
     */
    public function reduce(mixed $initialResult, Closure $updateAccumulatingResult)
    {
        return $this->sequenceReduce($initialResult, $updateAccumulatingResult);
    }

    /**
     * @template Result
     * Returns a new dictionary containing the keys of this dictionary with the values transformed by the given closure.
     * @param Closure(Element, string=): Result $transform A closure that transforms a value. transform accepts each value of the dictionary as its parameter and returns a transformed value of the same or of a different type.
     * @return Dictionary<Result> A dictionary containing the keys and transformed values of this dictionary.
     */
    public function mapValues(Closure $transform): Dictionary
    {
        $instance = new Dictionary();
        foreach ($this as $key => $value) {
            /** @psalm-suppress InvalidArgument */
            $instance[$key] = $transform($value, $key);
        }
        return $instance;
    }

    /**
     * @template Result
     * Returns an array containing the results of mapping the given closure over the sequence's elements.
     * @param Closure(Element, string=): Result $transform A mapping closure. transform accepts an element of this sequence as its parameter and returns a transformed value of the same or of a different type.
     * @return ArrayClass<Result> An array containing the transformed elements of this sequence.
     * @psalm-suppress ImplementedReturnTypeMismatch
     * @phpstan-ignore-next-line
     */
    public function map(Closure $transform): ArrayClass
    {
        $array = new ArrayClass();
        foreach ($this as $key => $value) {
            $array[] = $transform($value, $key);
        }
        return $array;
    }

    /**
     * @template Result
     * Returns an array containing the non-nil results of calling the given transformation with each element of this sequence.
     * Use this method to receive an array of non-optional values when your transformation produces an optional value.
     * @param Closure(Element, string=): Result $transform A closure that accepts an element of this sequence as its argument and returns an optional value.
     * @return ArrayClass<Result> An array of the non-nil results of calling transform with each element of the sequence.
     * @psalm-suppress ImplementedReturnTypeMismatch
     * @phpstan-ignore-next-line
     */
    public function compactMap(Closure $transform): ArrayClass
    {
        $array = new ArrayClass();
        foreach ($this as $key => $value) {
            $result = $transform($value, $key);
            if ($result !== null) {
                $array[] = $result;
            }
        }
        return $array;
    }

    /**
     * @template Result
     * Returns a new dictionary containing only the key-value pairs that have non-nil values as the result of transformation by the given closure.
     * @param Closure(Element, string=): Result $transform A closure that transforms a value. transform accepts each value of the dictionary as its parameter and returns an optional transformed value of the same or of a different type.
     * @return Dictionary<Result> A dictionary containing the keys and non-nil transformed values of this dictionary.
     */
    public function compactMapValues(Closure $transform): Dictionary
    {
        $instance = new Dictionary();
        foreach ($this as $key => $element) {
            /** @var Element $result */
            $result = $transform($element, $key);
            if ($result !== null) {
                $instance[$key] = $result;
            }
        }
        return $instance;
    }

    /**
     * @template Result
     * Returns a Collection containing the concatenated results of calling the given transformation with each element of this collection.
     * @param Closure(Element, string=): iterable<Result> $transform
     * @return ArrayClass<Result>
     * @psalm-suppress ImplementedReturnTypeMismatch
     * @phpstan-ignore-next-line
     */
    public function flatMap(Closure $transform): ArrayClass
    {
        $array = new ArrayClass();
        foreach ($this as $idx => $element) {
            $array->appendContentsOf($transform($element, $idx));
        }
        return $array;
    }

    /**
     * Returns the first index in which an element of the collection satisfies the given predicate.
     * @param Closure(Element): bool $where A closure that takes an element as its argument and returns a Boolean value that indicates whether the passed element represents a match.
     * @return string|null The index of the first element for which predicate returns true.
     * If no elements in the collection satisfy the given predicate, returns nil.
     */
    public function firstIndex(Closure $where): ?string
    {
        $k = $this->keys;
        $v = $this->values;
        $i = $this->startIndex();
        $end = $this->endIndex();
        while ($i !== $end) {
            if ($where($v[$i])) {
                return $k[$i];
            }
            $this->formIndexAfter($i);
        }
        return null;
    }

    /**
     * Returns the first index where the specified value appears in the collection.
     * @param Element $element An element to search for in the collection.
     * @return string|null The first index where element is found. If element is not found in the collection, returns nil.
     */
    public function indexOf(mixed $element): ?string
    {
        return $this->collectionIndexOf($element);
    }

    /**
     * @param string $index
     * @return Element|null
     */
    public function elementAt(mixed $index)
    {
        return $this->valueForKey($index);
    }

    /**
     * Returns the first element of the collection that satisfies the given predicate.
     * @param Closure(Element, string=): bool|null $where A closure that takes an element of the collection as its argument and returns a Boolean value indicating whether the element is a match.
     * @return Element|null The first element of the collection that satisfies predicate, or nil if there is no element that satisfies predicate.
     */
    public function first(Closure $where = null)
    {
        return $this->sequenceFirst($where);
    }

    /**
     * Returns a Collection containing, in order, the elements of the collection that satisfy the given predicate.
     * @param Closure(Element, string=, bool=): bool $isIncluded
     * @return Dictionary<Element>
     */

    public function filter(Closure $isIncluded): self
    {
        $instance = new Dictionary();
        foreach ($this as $i => $e) {
            $stop = false;
            if ($isIncluded($e, $i, $stop)) {
                $instance[$i] = $e;
            }
            /** @psalm-suppress TypeDoesNotContainType */
            if (/** @phpstan-ignore-line */ $stop) {
                break;
            }
        }
        return $instance;
    }

    /**
     * Returns a Collection containing, in order, the elements of the collection that satisfy the given predicate.
     * @param Predicate $predicate
     * @return Dictionary<Element>
     */
    public function filtered(Predicate $predicate): Dictionary
    {
        return $this->collectionFiltered($predicate);
    }

    /**
     * Sorts the collection in place.
     * @param Closure(Element, Element): int|null $by
     * @return Dictionary<Element>
     */
    public function sort(?Closure $by = null): Dictionary
    {
        $by ??= fn(mixed $e0, mixed $e1): int => compare($e0, $e1);
        uasort($this->reserved, $by);
        return $this;
    }

    /**
     * Returns a copy of the receiving sequence sorted as specified by a given array of sort descriptors.
     * @param iterable<SortDescriptor> $descriptors A sequence of {@see SortDescriptor} objects.
     * @return Dictionary<Element> A copy of the receiving sequence sorted as specified by descriptors.
     */
    public function sorted(iterable $descriptors): Dictionary
    {
        return $this->collectionSorted($descriptors);
    }

    /**
     * Returns a Boolean value indicating whether every element of a sequence satisfies a given predicate.
     * @param Closure(Element, string=): bool $predicate A closure that takes an element of the sequence as its argument and returns a Boolean value that indicates whether the passed element satisfies a condition.
     * @return bool {@see true} if the sequence contains an element that satisfies predicate; otherwise, {@see false}.
     */

    public function allSatisfy(Closure $predicate): bool
    {
        return $this->collectionAllSatisfy($predicate);
    }

    /**
     * Returns the elements of this sequence of sequences, concatenated.
     * @return FlattenSequence<Element> A flattened view of the elements of this sequence of sequences.
     * @psalm-suppress LessSpecificReturnStatement, MoreSpecificReturnType
     */
    public function joined(): FlattenSequence
    {
        return new FlattenSequence($this);
    }

    /**
     * Merges the given dictionary into this dictionary, using a combining closure to determine the value for any duplicate keys.
     *
     * Use the combine closure to select a value to use in the updated dictionary, or to combine existing and new values.
     * As the key-values pairs in other are merged with this dictionary, the combine closure is called with the current and new values for any duplicate keys that are encountered.
     * @param Dictionary<Element> $other A dictionary to merge.
     * @param Closure(Element, Element): Element|null $combine A closure that takes the current and new values for any duplicate keys.
     * The closure returns the desired value for the final dictionary.
     */
    public function merge(Dictionary $other, ?Closure $combine = null): void
    {
        foreach ($other as $key => $value) {
            $new = $value;
            $current = $this[$key];
            if (($current !== null) && ($combine !== null)) {
                $new = $combine($current, $value);
            }
            if ($new !== null) {
                $this[$key] = $new;
            }
        }
    }

    /**
     * Creates a dictionary by merging the given dictionary into this dictionary, using a combining closure to determine the value for duplicate keys.
     * @param Dictionary<Element> $other A dictionary to merge.
     * @param Closure(Element, Element): Element|null $combine A closure that takes the current and new values for any duplicate keys.
     * The closure returns the desired value for the final dictionary
     * @return Dictionary<Element> A new dictionary with the combined keys and values of this dictionary and other.
     */
    public function merging(Dictionary $other, ?Closure $combine = null): Dictionary
    {
        $instance = clone $this;
        $instance->merge($other, $combine);
        return $instance;
    }

    /**
     * Returns the value associated with a given key.
     * @param string $key The key for which to return the corresponding value.
     * @return Element|null The value associated with key.
     */
    public function valueForKey(string $key)
    {
        return $this->offsetGet($key);
    }

    /**
     * As on {@see valueForKey()} but for case-insensitive key.
     * @return Element|null
     */
    public function valueForCaseInsensitiveKey(string $key)
    {
        if ($this->offsetExists($key)) {
            return $this->offsetGet($key);
        }
        return $this->first(fn(mixed $e, string $k): bool => string_is_equal($k, $key, CompareOptions::caseInsensitive));
    }

    /**
     * Adds a given key-value pair to the dictionary.
     * @param Element|null $value The value for key.
     * @param string $key The key for value.
     */
    public function setValueForKey(mixed $value, string $key): void
    {
        if ($value === null) {
            $this->offsetUnset($key);
        } else {
            $this->offsetSet($key, $value);
        }
    }

    /**
     * Updates the value stored in the dictionary for the given key, or adds a new key-value pair if the key does not exist.
     *
     * Use this method instead of key-based subscripting when you need to know whether the new value supplants the value of an existing key. If the value of an existing key is updated, updateValue() returns the original value.
     * @param Element $value The new value to add to the dictionary.
     * @param string $key The key to associate with value. If key already exists in the dictionary, value replaces the existing associated value. If key isn't already a key of the dictionary, the (key, value) pair is added.
     * @return Element The value that was replaced, or nil if a new key-value pair was added.
     */
    public function updateValue(mixed $value, string $key)
    {
        $current = $this->valueForKey($key);
        $this->setValueForKey($value, $key);
        return $current;
    }

    /**
     * Removes the given key and its associated value from the dictionary.
     *
     * If the key is found in the dictionary, this method returns the key's associated value. On removal, this method invalidates all indices with respect to the dictionary.
     * @param string $key The key to remove along with its associated value.
     * @return Element|null The value that was removed, or nil if the key was not present in the dictionary.
     */
    public function removeValueForKey(string $key)
    {
        $value = $this->offsetGet($key);
        $this->offsetUnset($key);
        return $value;
    }

    /**
     * Removes all the elements that satisfy the given predicate.
     * @param Closure(Element, string=): bool|null $where A closure that takes an element of the sequence as its argument and returns a Boolean value indicating whether the element should be removed from the collection.
     */
    public function removeAll(Closure $where = null): void
    {
        if ($where === null) {
            $this->reserved = [];
            return;
        }
        $this->reserved = $this->filter(fn(mixed $e, string $i): bool => !$where($e, $i))->reserved;
    }

    /**
     * @param Dictionary<Element> $dictionary
     */
    public function setDictionary(Dictionary $dictionary): void
    {
        $this->reserved = $dictionary->toArray();
    }

    public function isEqual(mixed $other): bool
    {
        if ($other instanceof Dictionary) {
            return $this->keys->isEqual($other->keys) && $this->values->isEqual($other->values);
        }
        return false;
    }

    /**
     * @return array<string, Element>
     */
    public function toArray(): array
    {
        return $this->reserved;
    }

    public function description(): string
    {
        return sprintf("[%s]", $this->isEmpty() ? ":" : $this->mapValues(fn(mixed $value, string $key): string => sprintf("%s: %s", $key, human_readable_value($value)))->values->join(", "));
    }

    /**
     * @return Traversable<string, Element>
     */
    public function getIterator(): Traversable
    {
        return new ArrayIterator($this->reserved);
    }

    /**
     * @param string $offset
     */
    public function offsetExists(mixed $offset): bool
    {
        return array_key_exists($offset, $this->reserved);
    }

    /**
     * @param string $offset
     * @return Element|null
     */
    public function offsetGet(mixed $offset): mixed
    {
        return $this->reserved[$offset] ?? null;
    }

    /**
     * @param string $offset
     * @param Element|null $value
     */
    public function offsetSet(mixed $offset, mixed $value): void
    {
        if ($value === null) {
            unset($this->reserved[$offset]);
            return;
        }
        $this->reserved[$offset] = $value;
    }

    /**
     * @param string $offset
     */
    public function offsetUnset(mixed $offset): void
    {
        if (!$this->offsetExists($offset)) {
            return;
        }
        unset($this->reserved[$offset]);
    }
}
