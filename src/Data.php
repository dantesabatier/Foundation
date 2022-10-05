<?php

namespace Sabatier\Foundation;

use Closure;
use Exception;
use Iterator;
use JetBrains\PhpStorm\ArrayShape;
use JetBrains\PhpStorm\ExpectedValues;
use JetBrains\PhpStorm\Pure;

/**
 * Class Data
 * A byte buffer in memory.
 * The Data value type allows simple byte buffers to take on the behavior of Foundation objects. You can create empty or pre-populated buffers from a variety of sources and later add or remove bytes. You can filter and sort the content, or compare against other buffers. You can manipulate subranges of bytes and iterate over some or all of them.
 * @implements RangeReplaceableCollection<int>
 * @implements Iterator<int, int>
 * @package Sabatier\Foundation
 * @property-read string|null $bytes A pointer to the data object's contents.
 * @property-read int $length The number of bytes contained by the data object.
 */
class Data extends ObjectClass implements RangeReplaceableCollection, Iterator
{
    use RangeReplaceableCollectionAlgorithms {
        contains as protected sequenceContains;
        containsElement as protected sequenceContainsElement;
        first as protected sequenceFirst;
        last as protected sequenceLast;
        min as protected sequenceMin;
        max as protected sequenceMax;
        reduce as protected sequenceReduce;
        map as protected sequenceMap;
        compactMap as protected sequenceCompactMap;
        flatMap as protected sequenceFlatMap;
        toArray as protected sequenceToArray;
        randomElement as protected collectionRandomElement;
        firstIndex as protected collectionFirstIndex;
        lastIndex as protected collectionLastIndex;
        indexOf as protected collectionIndexOf;
        filter as protected collectionFilter;
        filtered as protected collectionFiltered;
        sorted as protected collectionSorted;
        allSatisfy as protected collectionAllSatisfy;
        joined as protected collectionJoined;
        drop as protected mutableCollectionDrop;
        dropFirst as protected mutableCollectionDropFirst;
        dropLast as protected mutableCollectionDropLast;
    }

    private string $reserved;
    private int $position = 0;

    public function __construct(string $string = '', ?string $base64String = null)
    {
        $this->reserved = $base64String ? base64_decode($base64String) : $string;
    }

    public function __clone()
    {
        $this->reserved = $this->description();
        $this->rewind();
    }

    #[ArrayShape(['reserved' => "string"])]
    public function __serialize(): array
    {
        return ['reserved' => $this->reserved];
    }

    public function __unserialize(array $data): void
    {
        $this->reserved = $data['reserved'];
    }

    public function __get(string $name)
    {
        return match ($name) {
            'bytes' => $this->length > 0 ? pack("i*", ...$this->toArray()) : null,
            'length' => strlen($this->reserved),
            default => $this->valueForUndefinedKey($name),
        };
    }

    public static function repeating(mixed $value, int $count): Data
    {
        assert($count >= 0, "invalid argument: count must be zero or greater");
        return new self(str_repeat(human_readable_value($value), $count));
    }

    /**
     * Sets a region of the data buffer to 0.
     * If range exceeds the bounds of the data, then the data is resized to fit.
     * @param Range $range The range in the data to set to 0.
     */
    public function resetBytes(Range $range): void
    {
        if ($n = ($range->upperBound - $this->count())) {
            $this->reserveCapacity($n);
        }
        $this->replaceSubrange($range, ArrayClass::repeating(0, $range->count()));
    }

    /**
     * Writes the contents of the data buffer to a location.
     * @param URL $url The location to write the data into.
     * @param int $options Options for writing the data. Default value is 0.
     * @throws Exception
     */
    public function write(URL $url, #[ExpectedValues(flagsFromClass: DataWritingOptions::class)] int $options = DataWritingOptions::atomic | DataWritingOptions::noFileProtection): void
    {
        $path = $url->path;
        $fileManager = FileManager::default();
        if ($options & DataWritingOptions::atomic) {
            $directory = $fileManager->temporaryDirectory;
            if (!$fileManager->fileExists($directory->path)) {
                $fileManager->createDirectory($directory, true);
            }
            $temporaryURL = $directory->appendingPathComponent($url->lastPathComponent);
            if ($fileManager->createFile($temporaryURL->path, $this->reserved)) {
                if ($fileManager->fileExists($path)) {
                    $fileManager->removeItem($url);
                }
                $fileManager->moveItem($temporaryURL, $url);
            }
        } elseif ($options & DataWritingOptions::withoutOverwriting) {
            if ($fileManager->fileExists($path)) {
                throw new Exception((string)(new Error(CocoaErrorDomain, 516, new Dictionary([FilePathErrorKey => $path]))));
            }
            $fileManager->createFile($path, $this->reserved);
        }
    }

    /**
     * Returns Base-64 encoded data.
     * @return Data The Base-64 encoded data.
     */
    public function base64EncodedData(#[ExpectedValues(flagsFromClass: DataBase64EncodingOptions::class)] int $options = 0): Data
    {
        return new Data(base64String: $this->base64EncodedString($options));
    }

    /**
     * Returns a Base-64 encoded string.
     * @return string The Base-64 encoded string.
     */
    public function base64EncodedString(/** @noinspection PhpUnusedParameterInspection */ #[ExpectedValues(flagsFromClass: DataBase64EncodingOptions::class)] int $options = 0): string
    {
        return base64_encode($this->reserved);
    }

    /**
     * Copies a subset of the contents of the data to memory.
     * @param string|null $pointer A pointer to the buffer you wish to copy the bytes into.
     * @param Range $range The range in the Data to copy.
     */
    public function copyBytes(?string &$pointer, Range $range): void
    {
        $pointer = $this->subdata($range)->bytes;
    }

    /**
     * Appends the specified data to the end of this data.
     */
    public function appendData(Data $data): void
    {
        $this->reserved .= $data->reserved;
    }

    /**
     * Appends the specified data to the end of this data.
     * @param int $element
     */
    public function append(mixed $element): void
    {
        $index = $this->indexOf(0);
        if ($index !== null) {
            $this->removeAt($index);
        }
        $this->reserved .= chr($element);
    }

    /**
     * Appends the bytes in the specified array to the end of the data.
     * @param iterable<int> $newElements
     */
    public function appendContentsOf(iterable $newElements): void
    {
        $this->insertContentsOf($newElements);
    }

    /**
     * Prepares the collection to store the specified number of elements, when doing so is appropriate for the underlying type.
     * @param int $n The requested number of elements to store.
     */
    public function reserveCapacity(int $n): void
    {
        $this->appendContentsOf(ArrayClass::repeating(0, $n));
    }

    /**
     * Returns a Boolean value indicating whether the sequence contains an element that satisfies the given predicate.
     * @param Closure(int, int): bool $predicate A closure that takes an element of the sequence as its argument and returns a Boolean value that indicates whether the passed element represents a match.
     * @return bool true if the sequence contains an element that satisfies predicate; otherwise, false.
     */
    public function contains(Closure $predicate): bool
    {
        return $this->sequenceContains($predicate);
    }

    /**
     * Returns a bool value indicating whether the sequence contains an element that satisfies the given predicate.
     * Available when Element conforms to {@see Equatable}.
     * @param int $element The element to find in the sequence.
     * @return bool {@see true} if the element was found in the sequence; otherwise, {@see false}.
     */
    public function containsElement(mixed $element): bool
    {
        return $this->sequenceContainsElement($element);
    }

    /**
     * Returns the minimum element in the sequence.
     * @return int|null The sequence's minimum element. If the sequence has no elements, returns nil.
     */
    public function min(): ?int
    {
        return $this->sequenceMin();
    }

    /**
     * Returns the maximum element in the sequence.
     * @return int|null The sequence's maximum element. If the sequence has no elements, returns nil.
     */
    public function max(): ?int
    {
        return $this->sequenceMax();
    }

    /**
     * Returns the result of combining the elements of the sequence using the given closure.
     * Use the {@see reduce()} method to produce a single value from the elements of an entire sequence.
     * For example, you can use this method on an array of integers to filter adjacent equal entries or count frequencies.
     * @template Result
     * @param Result $initialResult The value to use as the initial accumulating value.
     * @param Closure(Result, int, int=): Result $updateAccumulatingResult A closure that updates the accumulating value with an element of the sequence.
     * @return Result The final accumulated value. If the sequence has no elements, the result is initialResult.
     */
    public function reduce(mixed $initialResult, Closure $updateAccumulatingResult)
    {
        return $this->sequenceReduce($initialResult, $updateAccumulatingResult);
    }

    /**
     * Returns a Collection containing the results of mapping the given closure over the collection's elements.
     * @param Closure(int, int=): int $transform
     * @return Data
     * @psalm-suppress ImplementedReturnTypeMismatch, MoreSpecificImplementedParamType
     */
    public function map(Closure $transform): Data
    {
        return $this->sequenceMap($transform);
    }

    /**
     * Returns a Collection containing the non-nil results of calling the given transformation with each element of this collection.
     * @param Closure(int, int=): int $transform
     * @return Data
     * @psalm-suppress ImplementedReturnTypeMismatch, MoreSpecificImplementedParamType
     */
    public function compactMap(Closure $transform): Data
    {
        return $this->sequenceCompactMap($transform);
    }

    /**
     * Returns a Collection containing the concatenated results of calling the given transformation with each element of this collection.
     * @param Closure(int, int=): iterable<int> $transform
     * @return Data
     * @psalm-suppress ImplementedReturnTypeMismatch, MoreSpecificImplementedParamType
     */
    public function flatMap(Closure $transform): Data
    {
        return $this->sequenceFlatMap($transform);
    }

    /**
     * Returns the first element of the collection that satisfies the given predicate.
     * @param Closure(int, int=): bool|null $where A closure that takes an element of the collection as its argument and returns a Boolean value indicating whether the element is a match.
     * @return int|null The first element of the collection that satisfies predicate, or nil if there is no element that satisfies predicate.
     */
    public function first(Closure $where = null): ?int
    {
        return $this->sequenceFirst($where);
    }

    /**
     * Returns the last element of the collection that satisfies the given predicate.
     * @param Closure(int, int=): bool|null $where A closure that takes an element of the collection as its argument and returns a Boolean value indicating whether the element is a match.
     * @return int|null The last element of the collection that satisfies predicate, or nil if there is no element that satisfies predicate.
     */
    public function last(Closure $where = null): ?int
    {
        return $this->sequenceLast($where);
    }

    /**
     * Returns the first index in which an element of the collection satisfies the given predicate.
     * @param Closure(int): bool $where A closure that takes an element as its argument and returns a Boolean value that indicates whether the passed element represents a match.
     * @return int|null The index of the first element for which predicate returns true.
     * If no elements in the collection satisfy the given predicate, returns nil.
     */
    public function firstIndex(Closure $where): ?int
    {
        return $this->collectionFirstIndex($where);
    }

    /**
     * Returns the last index in which an element of the collection satisfies the given predicate.
     * @param Closure(int): bool $where A closure that takes an element as its argument and returns a Boolean value that indicates whether the passed element represents a match.
     * @return int|null The index of the last element for which predicate returns true.
     * If no elements in the collection satisfy the given predicate, returns nil.
     */
    public function lastIndex(Closure $where): ?int
    {
        return $this->collectionLastIndex($where);
    }

    /**
     * Returns the first index where the specified value appears in the collection.
     * @param int $element An element to search for in the collection.
     * @return int|null The first index where element is found. If element is not found in the collection, returns nil.
     */
    public function indexOf(mixed $element): ?int
    {
        return $this->collectionIndexOf($element);
    }

    /**
     * @param int $index
     * @return int|null
     */
    public function elementAt(mixed $index): ?int
    {
        return null;
    }

    /**
     * Returns a random element of the collection, using the given generator as a source for randomness.
     * @param RandomNumberGenerator $generator The random number generator to use when choosing a random element.
     * @return int|null A random element from the collection. If the collection is empty, the method returns nil.
     */
    public function randomElement(RandomNumberGenerator $generator = new SystemRandomNumberGenerator()): ?int
    {
        return $this->collectionRandomElement($generator);
    }

    /**
     * Returns a new collection of the same type containing, in order, the elements of the original collection that satisfy the given predicate.
     * @param Closure(int, int=, bool=): bool $isIncluded
     * @return Data
     */
    public function filter(Closure $isIncluded): Data
    {
        return $this->collectionFilter($isIncluded);
    }

    /**
     * Evaluates a given predicate against each object in the receiving and returns a new collection containing the objects for which the predicate returns true.
     * @param Predicate $predicate The predicate against which to evaluate the receiving collection's elements.
     * @return Data A new collection containing the objects in the receiving array for which predicate returns true.
     * Objects in the resulting array appear in the same order as they do in the receiver.
     */
    public function filtered(Predicate $predicate): Data
    {
        return $this->collectionFiltered($predicate);
    }

    /**
     * Returns a Boolean value indicating whether every element of a sequence satisfies a given predicate.
     * @param Closure(int, int=): bool $predicate A closure that takes an element of the sequence as its argument and returns a Boolean value that indicates whether the passed element satisfies a condition.
     * @return bool {@see true} if the sequence contains an element that satisfies predicate; otherwise, {@see false}.
     */
    public function allSatisfy(Closure $predicate): bool
    {
        return $this->collectionAllSatisfy($predicate);
    }

    /**
     * Sorts the collection in place.
     * @param Closure(int, int): int $by
     * @return Data
     */
    public function sort(Closure $by): Data
    {
        $bytes = $this->toArray();
        usort($bytes, $by);
        $this->reserved = implode(array_map("chr", $bytes));
        return $this;
    }

    /**
     * Returns a copy of the receiving collection sorted as specified by a given array of sort descriptors.
     * @param iterable<SortDescriptor> $descriptors A collection of {@see SortDescriptor} objects.
     * @return Data A copy of the receiving collection sorted as specified by descriptors.
     */
    public function sorted(iterable $descriptors): Data
    {
        return $this->collectionSorted($descriptors);
    }

    /**
     * Inserts the value into the collection at the specified position.
     * The new element is inserted before the element currently at the specified index.
     * If you pass the collection's endIndex property as the index parameter, the new element is appended to the collection.
     * @param int $element The new element to insert into the collection.
     * @param int $at The position at which to insert the new element. index must be a valid index into the collection.
     */
    public function insert(mixed $element, int $at): void
    {
        $this->reserved = sprintf("%s%s%s", substring_to_index($this->reserved, $at), chr($element), substring_from_index($this->reserved, $at));
    }

    /**
     * Inserts the elements of a sequence into the collection at the specified position.
     * The new elements are inserted before the element currently at the specified index.
     * If you pass the collection's endIndex property as the index parameter, the new elements are appended to the collection.
     * @param iterable<int, int> $newElements The new elements to insert into the collection.
     * @param int $at The position at which to insert the new elements. index must be a valid index of the collection.
     */
    public function insertContentsOf(iterable $newElements, int $at = NotFound): void
    {
        foreach ($newElements as $idx => $element) {
            if ($at !== NotFound) {
                $this->insert($element, $at + $idx);
            } else {
                $this->append($element);
            }
        }
    }

    /**
     * Removes the given element and any elements subsumed by the given element.
     * @param int $element
     */
    public function remove(mixed $element): void
    {
        $index = $this->indexOf($element);
        if ($index !== null) {
            $this->removeAt($index);
        }
    }

    /**
     * Removes and returns the element at the specified position.
     * @param int $index The position of the element to remove. position must be a valid index of the collection that is not equal to the collection's end index.
     * @return int The removed element.
     */
    public function removeAt(int $index): int
    {
        $b = $this->offsetGet($index);
        $this->offsetUnset($index);
        return $b;
    }

    /**
     * Removes all the elements that satisfy the given predicate.
     * @param Closure(int, int=): bool|null $where A closure that takes an element of the sequence as its argument and returns a Boolean value indicating whether the element should be removed from the collection.
     */
    public function removeAll(Closure $where = null): void
    {
        if ($where === null) {
            $this->reserved = '';
            return;
        }
        $this->reserved = $this->filter(fn(mixed $e, int $i): bool => !$where($e, $i))->reserved;
    }

    /**
     * This method has the effect of removing the specified range of elements from the collection and inserting the new elements at the same location.
     * The number of new elements need not match the number of elements being removed.
     * @param Range $subrange The subrange of the collection to replace. The start and end of a subrange must be valid indices of the collection.
     * @param Collection<int, int> $newElements The new elements to add to the collection.
     */
    public function replaceSubrange(Range $subrange, Collection $newElements): void
    {
        assert($subrange->count() <= $this->count() && $subrange->count() === $newElements->count(), "invalid argument: the range's upper bound must be less or equal to the count of the receiver and, range and collection must have the same number of elements");
        foreach ($subrange as $idx => $bound) {
            $this->removeAt($bound);
            $this->insert($newElements[$idx], $bound);
        }
    }

    public function removeSubrange(Range $subrange): void
    {
        $this->removeAll(fn(mixed $e, int $i): bool => $subrange->contains($i));
    }

    public function removeFirst(int $k): void
    {
        $this->removeAll(fn(mixed $e, int $i): bool => $i < $k);
    }

    public function removeLast(int $k): void
    {
        if ($k === 0) {
            return;
        }
        assert($k > 0, "Number of elements to remove should be non-negative");
        $e = $this->indexBefore($this->endIndex());
        $i = $e - $k;
        assert($i >= 0 && $i < $e, "Can't remove more items from a collection than it contains");
        while ($i < $e) {
            $this->offsetUnset($e);
            $this->formIndexBefore($e);
        }
    }

    /**
     * Removes and returns the first element of the collection.
     * @return int|null A member of the collection. If the collection is empty, returns nil.
     */
    public function popFirst(): ?int
    {
        if ($this->isEmpty()) {
            return null;
        }
        return $this->removeAt(0);
    }

    /**
     * Reverses the elements of the collection in place.
     */
    public function reverse(): Data
    {
        $this->reserved = implode(array_reverse(str_split($this->reserved)));
        return $this;
    }

    /**
     * Returns a collection containing the elements of this sequence in reverse order.
     * @return Data A collection containing the elements of this sequence in reverse order.
     */
    public function reversed(): Data
    {
        $instance = clone $this;
        $instance->reverse();
        return $instance;
    }

    public function join(string $separator): string
    {
        return implode($separator, $this->toArray());
    }

    /**
     * Returns the elements of this sequence of sequences, concatenated.
     * @return FlattenSequence<Data> A flattened view of the elements of this sequence of sequences.
     * @psalm-suppress LessSpecificReturnStatement, MoreSpecificReturnType
     */
    public function joined(): FlattenSequence
    {
        return $this->collectionJoined();
    }

    /**
     * Returns a subsequence by skipping elements while predicate returns true and returning the remaining elements.
     * @param Closure(int): bool $while A closure that takes an element of the sequence as its argument and returns true if the element should be skipped or false if it should be included.
     * Once the predicate returns false it will not be called again.
     */
    public function drop(Closure $while): Data
    {
        $data = clone $this;
        $data->removeAll($while);
        return $data;
    }

    /**
     * Returns a subsequence containing all but the given number of initial elements.
     * If the number of elements to drop exceeds the number of elements in the collection, the result is an empty subsequence.
     * @param int $k The number of elements to drop from the beginning of the collection. k must be greater than or equal to zero.
     * @return Data A subsequence starting after the specified number of elements.
     */
    public function dropFirst(int $k): Data
    {
        $data = clone $this;
        $data->removeFirst($k);
        return $data;
    }

    /**
     * Returns a subsequence containing all but the specified number of final elements.
     * If the number of elements to drop exceeds the number of elements in the collection, the result is an empty subsequence.
     * @param int $k The number of elements to drop off the end of the collection. k must be greater than or equal to zero.
     * @return Data A subsequence that leaves off the specified number of elements at the end.
     */
    public function dropLast(int $k): Data
    {
        $data = clone $this;
        $data->removeLast($k);
        return $data;
    }

    /**
     * Returns a subsequence, up to the specified maximum length, containing the initial elements of the collection.
     * If the maximum length exceeds the number of elements in the collection, the result contains all the elements in the collection.
     * @param int $maxLength The maximum number of elements to return. maxLength must be greater than or equal to zero.
     * @return Data A subsequence starting at the beginning of this collection with at most maxLength elements.
     */
    public function prefix(int $maxLength): Data
    {
        return new Data(substring_to_index($this->reserved, min($maxLength, $this->endIndex())));
    }

    /**
     * Returns a subsequence from the start of the collection through the specified position.
     * The resulting subsequence includes the element at the position specified by the through parameter.
     * @param int $position The index of the last element to include in the resulting subsequence. position must be a valid index of the collection that is not equal to the endIndex property.
     * @return Data A subsequence up to, and including, the given position.
     */
    public function prefixThrough(int $position): Data
    {
        return new Data(substring_to_index($this->reserved, $position));
    }

    /**
     * Returns a subsequence from the start of the collection up to, but not including, the specified position.
     * The resulting subsequence does not include the element at the position end.
     * @param int $end The “past the end” index of the resulting subsequence. end must be a valid index of the collection.
     * @return Data A subsequence up to, but not including, the end position.
     */
    public function prefixUpTo(int $end): Data
    {
        return new Data(substring_to_index($this->reserved, $end));
    }

    /**
     * Returns a subsequence containing the initial elements until predicate returns false and skipping the remaining elements.
     * @param Closure(int, int=): bool $predicate A closure that takes an element of the sequence as its argument and returns true if the element should be included or false if it should be excluded. Once the predicate returns false it will not be called again.
     */
    public function prefixWhile(Closure $predicate): Data
    {
        $data = clone $this;
        $data->filter(function (int $e, int $i, bool &$stop) use ($predicate): bool {
            $stop = !$predicate($e, $i);
            return !$stop;
        });
        return $data;
    }

    /**
     * Returns a subsequence, up to the given maximum length, containing the final elements of the collection.
     * If the maximum length exceeds the number of elements in the collection, the result contains the entire collection.
     * @param int $maxLength The maximum number of elements to return. maxLength must be greater than or equal to zero.
     * @return Data A subsequence terminating at the end of the collection with at most maxLength elements.
     */
    public function suffix(int $maxLength): Data
    {
        return new Data(substring_from_index($this->reserved, max($this->endIndex() - $maxLength, $this->startIndex())));
    }

    /**
     * Returns a subsequence from the specified position to the end of the collection.
     * @param int $start The index at which to start the resulting subsequence. start must be a valid index of the collection.
     * @return Data A subsequence starting at the start position.
     */
    public function suffixFrom(int $start): Data
    {
        return new Data(substring_from_index($this->reserved, $start));
    }

    /**
     * Returns a new copy of the data in a specified range.
     * @param Range $range The range to copy.
     */
    public function subdata(Range $range): Data
    {
        return new Data(substr($this->reserved, $range->lowerBound, $range->count()));
    }

    /**
     * @return int[]
     */
    public function toArray(): array
    {
        return $this->sequenceToArray();
    }

    /**
     * A textual description of the data.
     */
    public function description(): string
    {
        return sprintf("<%s>", $this->reserved);
    }

    public function jsonSerialize(): mixed
    {
        return $this->reserved;
    }

    public function current(): int
    {
        return ord($this->reserved[$this->key()]);
    }

    public function next(): void
    {
        $this->formIndexAfter($this->position);
    }

    #[Pure]
    public function key(): int
    {
        return $this->position;
    }

    public function valid(): bool
    {
        return $this->offsetExists($this->key());
    }

    public function rewind(): void
    {
        $this->position = 0;
    }

    public function count(): int
    {
        return $this->length;
    }

    /**
     * @param int $offset
     */
    public function offsetExists(mixed $offset): bool
    {
        return in_range($offset, $this->startIndex(), $this->endIndex());
    }

    public function offsetGet(mixed $offset): int
    {
        /** @psalm-suppress RedundantConditionGivenDocblockType */
        assert(is_int($offset), sprintf("invalid argument: expecting \"int\", \"%s\" given", typeof($offset)));
        assert($this->offsetExists($offset), sprintf("%s %s(%s) index \"%s\" out of bounds [%s...<%s]", $this->debugDescription(), __FUNCTION__, $offset, $offset, $this->startIndex(), $this->endIndex()));
        return ord($this->reserved[$offset]);
    }

    /**
     * @param int|null $offset
     * @param int $value
     */
    public function offsetSet(mixed $offset, mixed $value): void
    {
        if ($offset === null) {
            $this->reserved .= chr($value);
        } else {
            $this->reserved[$offset] = chr($value);
        }
    }

    /**
     * @param int $offset
     */
    public function offsetUnset(mixed $offset): void
    {
        if ($this->offsetExists($offset)) {
            $this->reserved = substr_replace($this->reserved, '', $offset, 1);
        }
    }
}
