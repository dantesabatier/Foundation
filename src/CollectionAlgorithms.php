<?php

namespace Sabatier\Foundation;

use Closure;
use Sabatier\Foundation\Predicates\Predicate;
use Sabatier\Foundation\Predicates\PredicateUtilities;

/**
 * @psalm-require-implements Collection
 */
trait CollectionAlgorithms
{
    use SequenceAlgorithms;

    public function offsetExists(mixed $offset): bool
    {
        return array_key_exists($offset, $this->reserved);
    }

    public function offsetGet(mixed $offset): mixed
    {
        assert(is_int($offset), sprintf("Invalid argument: expecting int, \"%s\" given", typeof($offset)));
        assert($this->offsetExists($offset), sprintf("%s %s(%s) index \"%s\" out of bounds [%s...<%s]", $this->debugDescription(), __FUNCTION__, $offset, $offset, $this->startIndex(), $this->endIndex()));
        return $this->reserved[$offset];
    }

    public function offsetSet(mixed $offset, mixed $value): void
    {
        if ($offset === null) {
            $this->reserved[] = $value;
            return;
        }
        $this->reserved[$offset] = $value;
    }

    public function offsetUnset(mixed $offset): void
    {
        if (!$this->offsetExists($offset)) {
            return;
        }
        unset($this->reserved[$offset]);
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

    public function elementAt(mixed $index)
    {
        return $this->reserved[$index] ?? null;
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
                $instance[] = $e;
            }
            /** @psalm-suppress TypeDoesNotContainType */
            if (/** @phpstan-ingore-line */ $stop) {
                break;
            }
        }
        return $instance;
    }

    public function filtered(Predicate $predicate): self
    {
        return $this->filter(fn(mixed $e): bool => $predicate->evaluate($e));
    }

    public function sort(?Closure $by = null): self
    {
        $by ??= fn(mixed $e0, mixed $e1): int => compare($e0, $e1);
        usort($this->reserved, $by);
        return $this;
    }

    public function sorted(iterable $descriptors): self
    {
        $instance = clone $this;
        $instance->sort(function (mixed $e1, mixed $e2) use ($descriptors): int {
            $result = ComparisonResult::orderedSame;
            /** @var SortDescriptor $descriptor */
            foreach ($descriptors as $descriptor) {
                if (($result = $descriptor->compareObject($e1, $e2)) !== ComparisonResult::orderedSame) {
                    break;
                }
            }
            return $result->value;
        });
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

    public function valueForKey(string $key): self
    {
        return $this->map(function (KeyValueCoding $e) use ($key): mixed {
            assert($e instanceof KeyValueCoding, sprintf("Invalid argument: expecting %s, \"%s\" given", KeyValueCoding::class, typeof($e)));
            return $e->valueForKey($key);
        });
    }

    public function setValueForKey(mixed $value, string $key): void
    {
        foreach ($this as $e) {
            assert($e instanceof KeyValueCoding, sprintf("Invalid argument: expecting %s, \"%s\" given", KeyValueCoding::class, typeof($e)));
            $e->setValueForKey($value, $key);
        }
    }

    public function valueForKeyPath(string $keyPath): mixed
    {
        if ($keyPath === "" || $keyPath[0] !== "@") {
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
