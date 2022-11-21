<?php

namespace Sabatier\Foundation;

use Closure;

/**
 * @psalm-require-implements MutableCollection
 */
trait MutableCollectionAlgorithms
{
    use BidirectionalCollectionAlgorithms;
    
    public function append(mixed $element): void
    {
        $this->reserved[] = $element;
    }
    
    public function appendContentsOf(iterable $newElements): void
    {
        $this->insertContentsOf($newElements);
    }
    
    public function insert(mixed $newElement): array
    {
        $oldElement = $this->first(fn(mixed $e): bool => $e instanceof Equatable ? $e->isEqual($newElement) : $e === $newElement);
        if ($oldElement === null) {
            $this->append($newElement);
            return ['inserted' => true, 'elementAfterInsert' => $newElement];
        }
        return ['inserted' => false, 'elementAfterInsert' => $oldElement];
    }
    
    public function insertAt(mixed $element, int $at): void
    {
        array_splice($this->reserved, $at, 0, [$element]);
        ksort($this->reserved);
    }
    
    public function insertContentsOf(iterable $newElements, int $at = NotFound): void
    {
        foreach ($newElements as $idx => $element) {
            if ($at !== NotFound) {
                $this->insertAt($element, $at + $idx);
            } else {
                $this->append($element);
            }
        }
    }
    
    public function update(mixed $element)
    {
        $index = $this->indexOf($element);
        if ($index !== null) {
            $member = $this[$index];
            $this->reserved[$index] = $element;
            return $member;
        }
        $this->append($element);
        return null;
    }
    
    public function remove(mixed $element): void
    {
        $index = $this->indexOf($element);
        if ($index !== null) {
            $this->removeAt($index);
        }
    }
    
    public function removeAt(int $index)
    {
        $element = $this->offsetGet($index);
        $this->offsetUnset($index);
        $this->reserved = array_values($this->reserved);
        return $element;
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
        $e = $this->endIndex();
        $i = $e - $k;
        assert($i >= 0 && $i < $e, "Can't remove more items from a collection than it contains");
        while ($i < $e) {
            $this->offsetUnset($i);
            $this->formIndexAfter($i);
        }
        $this->reserved = array_values($this->reserved);
    }
    
    public function removeAll(Closure $where = null): void
    {
        if ($where === null) {
            $this->reserved = [];
            return;
        }
        $this->reserved = $this->filter(fn(mixed $e, int $i): bool => !$where($e, $i))->reserved;
    }
    
    public function popFirst()
    {
        if ($this->isEmpty()) {
            return null;
        }
        return $this->removeAt(0);
    }
    
    public function popLast()
    {
        if ($this->isEmpty()) {
            return null;
        }
        return $this->removeAt($this->indexBefore($this->endIndex()));
    }
    
    public function drop(Closure $while): Slice
    {
        $start = $this->startIndex();
        $end = $this->endIndex();
        while (($start !== $end) && $while($this[$start])) {
            $this->formIndexAfter($start);
        }
        return new Slice($this, new Range($start, $end));
    }
    
    public function dropFirst(int $k): Slice
    {
        assert($k >= 0, "invalid argument: k must be zero or greater");
        return new Slice($this, new Range($k, $this->endIndex()));
    }
    
    public function dropLast(int $k): Slice
    {
        assert($k >= 0, "invalid argument: k must be zero or greater");
        return new Slice($this, new Range($this->startIndex(), max($this->endIndex() - $k, 0)));
    }
}
