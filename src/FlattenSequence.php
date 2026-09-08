<?php

declare(strict_types=1);

namespace Sabatier\Foundation;

use Closure;
use Generator;
use IteratorAggregate;
use Override;
use Sabatier\Foundation\Predicates\Predicate;
use Traversable;

/**
 * A lazy view that recursively concatenates arrays and Traversable segments from a base sequence.
 *
 * Dictionary instances remain elements because their keyed entries are not positional segments.
 * Iteration is lazy, although algorithms applied to the view may materialize their results.
 * @template Element
 * @implements Sequence<int, Element>
 * @implements IteratorAggregate<int, Element>
 */
final class FlattenSequence extends ObjectClass implements Sequence, IteratorAggregate
{
    use SequenceAlgorithms {
        reduce as private sequenceReduce;
        filtered as private sequenceFiltered;
    }

    #[Override]
    public string $description {
        get => sprintf("<%s %s <%s>>", $this->class, $this->base::class, human_readable_value($this->base));
    }
    /** @var int<0, max> */
    #[Override]
    public int $count {
        get => $this->count();
    }
    #[Override]
    public bool $isEmpty {
        get => $this->count === 0;
    }
    /** @var Element|null $first */
    #[Override]
    public mixed $first {
        get => $this->first();
    }
    /** @var list<Element> */
    #[Override]
    public array $array {
        get => iterator_to_array($this);
    }

    /**
     * Creates a lazy view that recursively concatenates the base sequence's segments.
     * @param Sequence<int, mixed> $base The sequence whose nested elements are flattened.
     */
    public function __construct(public readonly Sequence $base)
    {
    }

    /** @return array{base: Sequence<int, mixed>} */
    public function __serialize(): array
    {
        return ["base" => $this->base];
    }

    /** @param array{base: Sequence<int, mixed>} $data */
    public function __unserialize(array $data): void
    {
        $this->base = $data["base"];
    }

    /**
     * Returns the result of combining the elements of the sequence using the given closure.
     * Use the {@see reduce()} method to produce a single value from the elements of an entire sequence.
     * For example, you can use this method on an array of integers to filter adjacent equal entries or count frequencies.
     * @template Result
     * @param Result $initialResult The value to use as the initial accumulating value.
     * @param Closure(Result, mixed, int=): Result $updateAccumulatingResult A closure that updates the accumulating value with an element of the sequence.
     * @return Result The final accumulated value. If the sequence has no elements, the result is $initialResult.
     */
    #[Override]
    public function reduce(mixed $initialResult, Closure $updateAccumulatingResult)
    {
        return $this->sequenceReduce($initialResult, $updateAccumulatingResult);
    }

    /**
     * @template Result
     * Returns an ArrayClass containing the results of mapping the given closure over the flattened elements.
     * @param Closure(mixed, int=): Result $transform
     * @return ArrayClass<Result>
     */
    #[Override]
    public function map(Closure $transform): ArrayClass
    {
        return new ArrayClass($this)->map($transform);
    }

    /**
     * @template Result
     * Returns an ArrayClass containing the non-null results of transforming the flattened elements.
     *
     * @param Closure(mixed, int=): ?Result $transform
     * @return ArrayClass<Result>
     */
    #[Override]
    public function compactMap(Closure $transform): ArrayClass
    {
        return new ArrayClass($this)->compactMap($transform);
    }

    /**
     * @template Result
     * Returns an ArrayClass containing the concatenated results of transforming the flattened elements.
     * @param Closure(mixed, int=): iterable<Result> $transform
     * @return ArrayClass<Result>
     */
    #[Override]
    public function flatMap(Closure $transform): ArrayClass
    {
        return new ArrayClass($this)->flatMap($transform);
    }

    /**
     * Calls the given closure on each element in the sequence in the same order as a for-in loop.
     *
     * The two loops in the following example produce the same output:
     *
     * <code>
     * $numberWords = ["one", "two", "three"]
     * foreach ($numberWords as $word) {
     *     print "$word\n";
     * }
     * // Prints "one"
     * // Prints "two"
     * // Prints "three"
     * $numberWords->forEach(fn(string $word): void => print "$word\n");
     * // Same as above
     * </code>
     *
     * You cannot use a break or continue statement to exit the current call of the body closure or skip later calls.
     * @param Closure(Element, int=): void $body A closure that takes an element of the sequence as a parameter.
     */
    #[Override]
    public function forEach(Closure $body): void
    {
        /**
         * @var Element $e
         * @var int $i
         */
        foreach (clone $this as $i => $e) {
            $body($e, $i);
        }
    }

    /**
     * Returns an ArrayClass containing the flattened elements that satisfy the given predicate.
     *
     * Complexity: O(n), where n is the length of the sequence.
     * @param Closure(Element, int=, bool=): bool $isIncluded
     * @return ArrayClass<Element>
     */
    #[Override]
    public function filter(Closure $isIncluded): ArrayClass
    {
        return new ArrayClass($this)->filter($isIncluded);
    }

    /**
     * Returns a Collection containing, in order, the elements of the collection that satisfy the given predicate.
     * @param Predicate $predicate
     * @return ArrayClass<Element>
     */
    #[Override]
    public function filtered(Predicate $predicate): ArrayClass
    {
        /** @var ArrayClass<Element> */
        return $this->sequenceFiltered($predicate);
    }

    #[Override]
    public function getIterator(): Generator
    {
        return (function (): Generator {
            $recursive = function (iterable $iterable) use (&$recursive): Traversable {
                foreach ($iterable as $item) {
                    if (is_array($item) || ($item instanceof Traversable && !$item instanceof Dictionary)) {
                        foreach ($recursive($item) as $v) {
                            yield $v;
                        }
                    } else {
                        yield $item;
                    }
                }
            };
            foreach ($recursive($this->base) as $element) {
                yield $element;
            }
        })();
    }

    #[Override]
    public function count(): int
    {
        return iterator_count($this);
    }
}
