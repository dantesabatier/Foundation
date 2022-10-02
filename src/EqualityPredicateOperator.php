<?php

/**
 * Created by PhpStorm.
 * User: dante
 * Date: 24/07/20
 * Time: 11:44
 */

namespace Sabatier\Foundation;

use JetBrains\PhpStorm\ExpectedValues;
use JetBrains\PhpStorm\Pure;
use Stringable;

/** @internal */
class EqualityPredicateOperator extends PredicateOperator
{
    public readonly bool $isNegation;

    #[Pure]
    public function __construct(PredicateOperatorType $operatorType, ComparisonPredicateModifier $modifier = ComparisonPredicateModifier::direct, #[ExpectedValues(flagsFromClass: ComparisonPredicateOptions::class)] int $options = ComparisonPredicateOptions::none, bool $isNegation = false)
    {
        parent::__construct($operatorType, $modifier, $options);
        $this->isNegation = $isNegation;
    }

    public function performPrimitiveOperation(mixed $left, mixed $right): bool
    {
        if ($left instanceof Value) {
            $left = $left->value;
        }
        if ($right instanceof Value) {
            $right = $right->value;
        }
        $isNegation = $this->isNegation;
        if ($left === null && $right === null) {
            return !$isNegation;
        }
        if ($left === null || $right === null) {
            return $isNegation;
        }
        if ($left instanceof Stringable) {
            $left = (string)$left;
        }
        if ($right instanceof Stringable) {
            $right = (string)$right;
        }
        if (is_numeric($left)) {
            $left = (string)$left;
        }
        if (is_numeric($right)) {
            $right = (string)$right;
        }
        $options = $this->compareOptions();
        if ($options === CompareOptions::none || !is_string($left) || !is_string($right)) {
            return $isNegation xor ($left === $right);
        }
        if (string_is_equal($left, $right, $options)) {
            return !$isNegation;
        }
        return $isNegation;
    }
}
