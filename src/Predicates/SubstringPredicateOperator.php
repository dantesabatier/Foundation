<?php

/**
 * Created by PhpStorm.
 * User: dante
 * Date: 24/07/20
 * Time: 11:52
 */

namespace Sabatier\Foundation\Predicates;

use JetBrains\PhpStorm\ExpectedValues;
use Sabatier\Foundation\ArrayClass;
use Sabatier\Foundation\Set;
use function Sabatier\Foundation\human_readable_value;
use function Sabatier\Foundation\in_string;
use function Sabatier\Foundation\string_has_prefix;
use function Sabatier\Foundation\string_has_suffix;
use function Sabatier\Foundation\string_is_equal;
use function Sabatier\Foundation\typeof;

/** @internal */
class SubstringPredicateOperator extends StringPredicateOperator
{
    public function __construct(PredicateOperatorType $operatorType, ComparisonPredicateModifier $modifier = ComparisonPredicateModifier::direct, #[ExpectedValues(flagsFromClass: ComparisonPredicateOptions::class)] int $options = ComparisonPredicateOptions::none, public readonly SubstringPredicateOperatorPosition $position = SubstringPredicateOperatorPosition::beginsWith)
    {
        parent::__construct($operatorType, $modifier, $options);
    }

    public function performPrimitiveOperation(mixed $left, mixed $right): bool
    {
        if (Predicate::$debugDefault) {
            error_log(sprintf("Foundation: predicate operator %s: (%s)%s %s (%s)%s", $this->operatorType->name, typeof($left), human_readable_value($left), $this->symbol(), typeof($right), human_readable_value($right)));
        }
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
        assert(is_string($left) && is_string($right), sprintf("Cannot perform substring check on non-strings %s and %s", $left, $right));
        return match ($position) {
            SubstringPredicateOperatorPosition::beginsWith => string_has_prefix($left, $right, $options),
            SubstringPredicateOperatorPosition::endsWith => string_has_suffix($left, $right, $options),
            SubstringPredicateOperatorPosition::contains => in_string($left, $right, $options),
        };
    }
}
