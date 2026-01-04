<?php

namespace Sabatier\Foundation;

/**
 * @phpstan-require-implements SetAlgebra
 */
trait SetAlgebraAlgorithms
{
    use MutableCollectionAlgorithms;

    public function insert(mixed $newElement): array
    {
        $oldElement = $this->first(fn(mixed $e): bool => is_equal($e, $newElement));
        if ($oldElement === null) {
            $this[] = $newElement;
            return ["inserted" => true, "elementAfterInsert" => $newElement];
        }
        return ["inserted" => false, "elementAfterInsert" => $oldElement];
    }

    public function update(mixed $element)
    {
        $index = $this->indexOf($element);
        if ($index !== null) {
            $member = $this[$index];
            $this->reserved[$index] = $element;
            return $member;
        }
        $this[] = $element;
        return null;
    }

    public function member(mixed $element): mixed
    {
        return $this->first(fn(mixed $e): bool => is_equal($e, $element));
    }

    public function union(iterable $other): self
    {
        $instance = clone $this;
        $instance->formUnion($other);
        return $instance;
    }

    public function formUnion(iterable $other): void
    {
        foreach ($other as $element) {
            $this->append($element);
        }
    }

    public function intersection(SetAlgebra $other): self
    {
        $instance = clone $this;
        $instance->formIntersection($other);
        return $instance;
    }

    public function formIntersection(SetAlgebra $other): void
    {
        foreach (clone $this as $member) {
            if (!$other->containsElement($member)) {
                $this->remove($member);
            }
        }
    }

    public function symmetricDifference(SetAlgebra $other): self
    {
        $instance = clone $this;
        $instance->formSymmetricDifference($other);
        return $instance;
    }

    public function formSymmetricDifference(SetAlgebra $other): void
    {
        foreach ($other as $member) {
            if ($this->containsElement($member)) {
                $this->remove($member);
            } else {
                $this->append($member);
            }
        }
    }

    public function subtract(SetAlgebra $other): void
    {
        foreach ($other as $member) {
            $this->remove($member);
        }
    }

    public function subtracting(SetAlgebra $other): self
    {
        $instance = clone $this;
        $instance->subtract($other);
        return $instance;
    }

    public function isSubset(SetAlgebra $other): bool
    {
        if ($this->compare($other) != ComparisonResult::orderedAscending) {
            return false;
        }
        return $this->allSatisfy($other->containsElement(...));
    }

    public function isSuperset(SetAlgebra $other): bool
    {
        if ($this->compare($other) != ComparisonResult::orderedDescending) {
            return false;
        }
        return $this->allSatisfy($other->containsElement(...));
    }

    public function isDisjoint(SetAlgebra $other): bool
    {
        return !$this->contains($other->containsElement(...));
    }
}
