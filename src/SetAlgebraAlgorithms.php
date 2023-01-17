<?php

namespace Sabatier\Foundation;

/**
 * @psalm-require-implements SetAlgebra
 */
trait SetAlgebraAlgorithms
{
    use MutableCollectionAlgorithms;

    public function member(mixed $element): mixed
    {
        return $this->first(fn(mixed $e): bool => equivalent($e, $element));
    }

    public function union(SetAlgebra $other): self
    {
        $instance = clone $this;
        $instance->formUnion($other);
        return $instance;
    }

    public function formUnion(SetAlgebra $other): void
    {
        $this->appendContentsOf($other);
    }

    public function intersection(SetAlgebra $other): self
    {
        $instance = clone $this;
        $instance->formIntersection($other);
        return $instance;
    }

    public function formIntersection(SetAlgebra $other): void
    {
        foreach ($this as $member) {
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
        foreach ($this as $element) {
            if (!$other->containsElement($element)) {
                return false;
            }
        }
        return true;
    }

    public function isSuperset(SetAlgebra $other): bool
    {
        if ($this->compare($other) != ComparisonResult::orderedDescending) {
            return false;
        }
        foreach ($this as $element) {
            if (!$other->containsElement($element)) {
                return false;
            }
        }
        return true;
    }

    public function isDisjoint(SetAlgebra $other): bool
    {
        return !$this->contains(fn(mixed $member): bool => $other->containsElement($member));
    }
}
