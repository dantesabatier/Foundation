<?php
/**
 * Created by PhpStorm.
 * User: dante
 * Date: 24/07/20
 * Time: 11:52
 */

namespace Sabatier\Foundation;

use JetBrains\PhpStorm\ExpectedValues;

/** @internal */
class SubstringPredicateOperator extends StringPredicateOperator
{
    public function __construct(PredicateOperatorType $operatorType, ComparisonPredicateModifier $modifier = ComparisonPredicateModifier::direct, #[ExpectedValues(flagsFromClass: ComparisonPredicateOptions::class)] int $options = ComparisonPredicateOptions::none, public readonly SubstringPredicateOperatorPosition $position = SubstringPredicateOperatorPosition::beginsWith)
    {
        parent::__construct($operatorType, $modifier, $options);
    }

    public function performPrimitiveOperation(mixed $left, mixed $right): bool
    {
        if ($left === null || $right === null) {
            return false;
        }
        $options = $this->compareOptions();
        $position = $this->position;
        if ($left instanceof Set || $left instanceof ArrayClass) {
            if ($left->isEmpty()) {
                return false;
            }
            return match ($position) {
                SubstringPredicateOperatorPosition::beginsWith => string_is_equal($left[0], $right, $options),
                SubstringPredicateOperatorPosition::endsWith => string_is_equal($left[$left->indexBefore($left->endIndex())], $right, $options),
                SubstringPredicateOperatorPosition::contains => $left->contains(fn(string $string): bool => string_is_equal($string, $right, $options)),
            };
        }
        assert(is_string($left) && is_string($right), sprintf('cannot perform substring check on non-strings %s and %s', $left, $right));
        return match ($position) {
            SubstringPredicateOperatorPosition::beginsWith => string_has_prefix($left, $right, $options),
            SubstringPredicateOperatorPosition::endsWith => string_has_suffix($left, $right, $options),
            SubstringPredicateOperatorPosition::contains => in_string($left, $right, $options),
        };
    }
}
