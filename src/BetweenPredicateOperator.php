<?php

/**
 * Created by PhpStorm.
 * User: dante
 * Date: 24/07/20
 * Time: 11:43
 */

namespace Sabatier\Foundation;

/** @internal */
class BetweenPredicateOperator extends PredicateOperator
{
    public function performPrimitiveOperation(mixed $left, mixed $right): bool
    {
        if ($left instanceof ArrayClass) {
            $left = $left->first();
        }
        if ($left === null || $right === null) {
            return false;
        }
        assert($right instanceof ArrayClass && $right->count() === 2, sprintf("invalid argument: the right expression must be a \"%s\" with exactly two elements, \"%s\" given", ArrayClass::class, typeof($right)));
        return in_range($left, $right->first(), $right->last());
    }
}
