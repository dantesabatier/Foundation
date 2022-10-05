<?php

/**
 * @author Dante Sabatier <dantesabatier@me.com>
 * @version 1.0
 * @package Sabatier\Foundation
 */

namespace Sabatier\Foundation;

use InvalidArgumentException;
use JetBrains\PhpStorm\ExpectedValues;
use JetBrains\PhpStorm\Pure;

/** @internal */
class ComparisonPredicateOperator extends PredicateOperator
{
    #[Pure]
    public function __construct(PredicateOperatorType $operatorType, ComparisonPredicateModifier $modifier, #[ExpectedValues(flagsFromClass: ComparisonPredicateOptions::class)] int $options, public readonly PredicateOperatorType $variant)
    {
        parent::__construct($operatorType, $modifier, $options);
    }

    public function performPrimitiveOperation(mixed $left, mixed $right): bool
    {
        $variant = $this->variant;
        if ($left === null && $right === null) {
            return match ($variant) {
                PredicateOperatorType::lessThan, PredicateOperatorType::lessThanOrEqualTo => false,
                PredicateOperatorType::greaterThan, PredicateOperatorType::greaterThanOrEqualTo => true,
                default => throw new InvalidArgumentException(sprintf("invalid predicate operator variant: %s", $variant->name)),
            };
        }
        if ($left === null || $right === null) {
            return false;
        }
        $options = $this->compareOptions();
        if ($options !== CompareOptions::none && is_string($left) && is_string($right)) {
            $comparison = ComparisonResult::from(string_compare($left, $right, $options));
            return match ($variant) {
                PredicateOperatorType::lessThan => $comparison === ComparisonResult::orderedAscending,
                PredicateOperatorType::lessThanOrEqualTo => $comparison !== ComparisonResult::orderedDescending,
                PredicateOperatorType::greaterThan => $comparison === ComparisonResult::orderedDescending,
                PredicateOperatorType::greaterThanOrEqualTo => $comparison !== ComparisonResult::orderedAscending,
                default => throw new InvalidArgumentException(sprintf("invalid predicate operator variant: %s", $variant->name)),
            };
        }
        if ($left instanceof ObjectClass) {
            $left = $left->description();
        }
        if ($right instanceof ObjectClass) {
            $right = $right->description();
        }
        if (is_numeric($right) && is_string($left)) {
            $left = strlen($left);
        }
        if (is_numeric($left) && is_string($right)) {
            $right = strlen($right);
        }
        return match ($variant) {
            PredicateOperatorType::lessThan => $left < $right,
            PredicateOperatorType::lessThanOrEqualTo => $left <= $right,
            PredicateOperatorType::greaterThan => $left > $right,
            PredicateOperatorType::greaterThanOrEqualTo => $left >= $right,
            default => throw new InvalidArgumentException(sprintf("invalid predicate operator variant: %s", $variant->name)),
        };
    }
}
