<?php

/**
 * Created by PhpStorm.
 * User: dante
 * Date: 24/07/20
 * Time: 11:43
 */

namespace Sabatier\Foundation\Predicates;

use Sabatier\Foundation\ArrayClass;
use function Sabatier\Foundation\human_readable_value;
use function Sabatier\Foundation\in_range;
use function Sabatier\Foundation\typeof;

/** @internal */
class BetweenPredicateOperator extends PredicateOperator
{
    public function performPrimitiveOperation(mixed $left, mixed $right): bool
    {
        if (Predicate::$debugDefault) {
            error_log(sprintf("Foundation: predicate operator %s: (%s)%s %s (%s)%s", $this->operatorType->name, typeof($left), human_readable_value($left), $this->symbol(), typeof($right), human_readable_value($right)));
        }
        if ($left instanceof ArrayClass) {
            $left = $left->first();
        }
        if ($left === null || $right === null) {
            return false;
        }
        assert($right instanceof ArrayClass && $right->count() === 2, sprintf("Invalid argument: the right expression must be a \"%s\" with exactly two elements, \"%s\" given", ArrayClass::class, typeof($right)));
        return in_range($left, $right->first(), $right->last());
    }
}
