<?php

namespace Sabatier\Foundation;

use Closure;
use Countable;
use JetBrains\PhpStorm\Pure;

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

    #[Pure]
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

    #[Pure]
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
        while ($i != $end) {
            if ($where($this[$i])) {
                return $i;
            }
            $this->formIndexAfter($i);
        }
        return null;
    }

    public function indexOf(mixed $element): mixed
    {
        return $this->firstIndex(fn (mixed $e): bool => equivalent($e, $element));
    }

    public function distance(int $start, int $end): int
    {
        return $end - $start;
    }

    public function isEmpty(): bool
    {
        return $this->startIndex() == $this->endIndex();
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
        return $this->filter(fn (mixed $e): bool => $predicate->evaluate($e));
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
        return join($separator, $this->map(fn (mixed $e): string => human_readable_value($e))->toArray());
    }

    #[Pure]
    public function joined(): FlattenSequence
    {
        return new FlattenSequence($this);
    }

    public function compare(mixed $other): ComparisonResult
    {
        assert($other instanceof Countable);
        return ComparisonResult::from($this->count() <=> $other->count());
    }
}
