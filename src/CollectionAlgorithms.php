<?php

namespace Sabatier\Foundation;

use Closure;
use Countable;

/**
 * @psalm-require-implements Collection
 */
trait CollectionAlgorithms
{
    use SequenceAlgorithms;

    public function count(): int
    {
        return iterator_count($this);
    }

    public function startIndex(): int
    {
        return 0;
    }

    public function endIndex(): int
    {
        return $this->count();
    }

    public function indices(): Range
    {
        return new Range($this->startIndex(), $this->endIndex());
    }

    public function indexAfter(int $i): int
    {
        return $i + 1;
    }

    public function formIndexAfter(int &$i): void
    {
        $i += 1;
    }

    public function firstIndex(Closure $where): mixed
    {
        $i = $this->startIndex();
        $end = $this->endIndex();
        while ($i !== $end) {
            if ($where($this[$i])) {
                return $i;
            }
            $this->formIndexAfter($i);
        }
        return null;
    }

    /** @noinspection PhpMixedReturnTypeCanBeReducedInspection */
    public function indexOf(mixed $element): mixed
    {
        return $this->firstIndex(fn(mixed $e): bool => equivalent($e, $element));
    }

    public function distance(int $start, int $end): int
    {
        return $end - $start;
    }

    public function isEmpty(): bool
    {
        return $this->startIndex() === $this->endIndex();
    }

    public function filter(Closure $isIncluded): self
    {
        $instance = new self();
        foreach ($this as $i => $e) {
            $stop = false;
            if ($isIncluded($e, $i, $stop)) {
                if ($instance instanceof Dictionary) {
                    $instance[$i] = $e;
                } else {
                    $instance[] = $e;
                }
            }
            /** @psalm-suppress TypeDoesNotContainType */
            if ($stop) {
                break;
            }
        }
        return $instance;
    }

    public function filtered(Predicate $predicate): self
    {
        return $this->filter(fn(mixed $e): bool => $predicate->evaluate($e));
    }

    public function sorted(iterable $descriptors): self
    {
        $instance = clone $this;
        $instance->sort(
            function (mixed $e1, mixed $e2) use ($descriptors): int {
                $result = ComparisonResult::orderedSame;
                /** @var SortDescriptor $descriptor */
                foreach ($descriptors as $descriptor) {
                    if (($result = $descriptor->compareObject($e1, $e2)) !== ComparisonResult::orderedSame) {
                        break;
                    }
                }
                return $result->value;
            }
        );
        return $instance;
    }

    public function allSatisfy(Closure $predicate): bool
    {
        return $this->filter($predicate)->compare($this) === ComparisonResult::orderedSame;
    }

    public function join(string $separator): string
    {
        return implode($separator, $this->map(fn(mixed $e): string => human_readable_value($e))->toArray());
    }

    public function joined(): FlattenSequence
    {
        return new FlattenSequence($this);
    }

    public function compare(mixed $other): ComparisonResult
    {
        assert($other instanceof Countable);
        return ComparisonResult::from($this->count() <=> $other->count());
    }

    public function valueForKey(string $key): self
    {
        return $this->map(function (mixed $e) use ($key): mixed {
            assert($e instanceof KeyValueCoding); // @phpstan-ignore-line
            return $e->valueForKey($key);
        });
    }

    public function setValueForKey(mixed $value, string $key): void
    {
        foreach ($this as $e) {
            assert($e instanceof KeyValueCoding); // @phpstan-ignore-line
            $e->setValueForKey($value, $key);
        }
    }

    public function valueForKeyPath(string $keyPath): mixed
    {
        if (strlen($keyPath) === 0 || $keyPath[0] !== '@') {
            return parent::valueForKeyPath($keyPath);
        }
        $components = components_from_key_path($keyPath);
        $key = $components->key;
        $operator = kvc_operator_from_key($key);
        if (!$operator) {
            return null;
        }
        $value = $this;
        $remainderPath = $components->remainderPath;
        if ($remainderPath) {
            $value = parent::valueForKeyPath($remainderPath);
            assert($value instanceof self);
        }
        return match ($operator) {
            KeyValueOperator::averageKeyValueOperator, KeyValueOperator::countKeyValueOperator, KeyValueOperator::maximumKeyValueOperator, KeyValueOperator::minimumKeyValueOperator, KeyValueOperator::sumKeyValueOperator => PredicateUtilities::$operator($value),
            KeyValueOperator::distinctUnionOfArraysKeyValueOperator, KeyValueOperator::distinctUnionOfObjectsKeyValueOperator => new ArrayClass(new Set($value->joined())),
            KeyValueOperator::unionOfObjectsKeyValueOperator, KeyValueOperator::unionOfArraysKeyValueOperator => new ArrayClass($value->joined()),
            KeyValueOperator::distinctUnionOfSetsKeyValueOperator, KeyValueOperator::unionOfSetsKeyValueOperator => new Set($value->joined()),
            default => $this->valueForUndefinedKey($operator),
        };
    }
}
