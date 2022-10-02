<?php

/**
 * Created by PhpStorm.
 * User: dante
 * Date: 24/07/20
 * Time: 13:57
 */

namespace Sabatier\Foundation;

/** @internal */
class LikePredicateOperator extends MatchingPredicateOperator
{
    public function performPrimitiveOperation(mixed $left, mixed $right): bool
    {
        if ($left === null || $right === null) {
            return false;
        }
        assert(is_string($left) && is_string($right), sprintf('cannot perform substring check on non-strings %s and %s', human_readable_value($left), human_readable_value($right)));
        return string_is_equal($left, $right, $this->compareOptions());
    }
}
