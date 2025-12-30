<?php

/**
 * Created by PhpStorm.
 * User: dante
 * Date: 24/07/20
 * Time: 11:43
 */

namespace Sabatier\Foundation\Predicates;

use Override;
use Sabatier\Foundation\ArrayClass;
use function Sabatier\Foundation\in_range;
use function Sabatier\Foundation\typeof;

/** @internal */
final class BetweenPredicateOperator extends PredicateOperator
{
    #[Override]
    protected function performPrimitiveOperation(mixed $left, mixed $right): bool
    {
        if ($left instanceof ArrayClass) {
            $left = $left->first;
        }
        if ($left === null || $right === null) {
            return false;
        }
        assert($right instanceof ArrayClass && $right->count === 2, sprintf("Invalid argument: the right expression must be a \"%s\" with exactly two elements, \"%s\" given", ArrayClass::class, typeof($right)));
        return in_range($left, $right->first, $right->last);
    }
}
