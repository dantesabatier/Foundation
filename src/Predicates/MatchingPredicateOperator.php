<?php

/**
 * Created by PhpStorm.
 * User: dante
 * Date: 24/07/20
 * Time: 11:45
 */

namespace Sabatier\Foundation\Predicates;

use function Sabatier\Foundation\human_readable_value;
use function Sabatier\Foundation\string_matches;
use function Sabatier\Foundation\typeof;

/** @internal */
class MatchingPredicateOperator extends StringPredicateOperator
{
    public function performPrimitiveOperation(mixed $left, mixed $right): bool
    {
        if (Predicate::$debugDefault) {
            error_log(sprintf("Foundation: predicate operator %s: (%s)%s %s (%s)%s", $this->operatorType->name, typeof($left), human_readable_value($left), $this->symbol(), typeof($right), human_readable_value($right)));
        }
        if ($left === null || $right === null) {
            return false;
        }
        assert(is_string($left) && is_string($right), sprintf('Cannot perform substring check on non-strings %s and %s', human_readable_value($left), human_readable_value($right)));
        return string_matches($left, $right, $this->compareOptions());
    }
}
