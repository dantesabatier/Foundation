<?php

/**
 * Created by PhpStorm.
 * User: dante
 * Date: 24/07/20
 * Time: 11:43
 */

declare(strict_types=1);

namespace Sabatier\Foundation\Predicates;

use Override;
use Sabatier\Foundation\ArrayClass;
use function Sabatier\Foundation\compare;
use function Sabatier\Foundation\fatal_error;
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
        $right instanceof ArrayClass && $right->count === 2 ?: $right
                |> typeof(...)
                |> (fn(string $x): string => sprintf("Invalid argument: the right expression must be a \"%s\" with exactly two elements, \"%s\" given", ArrayClass::class, $x))
                |> fatal_error(...);
        // BETWEEN is inclusive at both ends, so in_range() is the wrong helper here: it is half-open by design (min <= value < max) and additionally requires max to exceed min, which rejects a single-point range like {5,5} outright.
        return compare($left, $right->first) >= 0 && compare($left, $right->last) <= 0;
    }
}
