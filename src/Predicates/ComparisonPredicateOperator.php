<?php

namespace Sabatier\Foundation\Predicates;

use JetBrains\PhpStorm\ExpectedValues;
use Sabatier\Foundation\CompareOptions;
use Sabatier\Foundation\ComparisonResult;
use Sabatier\Foundation\ObjectClass;
use function Sabatier\Foundation\fatal_error;
use function Sabatier\Foundation\string_compare;

/** @internal */
class ComparisonPredicateOperator extends PredicateOperator
{
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
                default => fatal_error("Invalid predicate operator variant: $variant->name")
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
                default => fatal_error("Invalid predicate operator variant: $variant->name")
            };
        }
        if ($left instanceof ObjectClass) {
            $left = $left->description();
        }
        if ($right instanceof ObjectClass) {
            $right = $right->description();
        }
        return match ($variant) {
            PredicateOperatorType::lessThan => $left < $right,
            PredicateOperatorType::lessThanOrEqualTo => $left <= $right,
            PredicateOperatorType::greaterThan => $left > $right,
            PredicateOperatorType::greaterThanOrEqualTo => $left >= $right,
            default => fatal_error("Invalid predicate operator variant: $variant->name")
        };
    }
}
