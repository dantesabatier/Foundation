<?php

/**
 * Created by PhpStorm.
 * User: dante
 * Date: 24/07/20
 * Time: 11:45
 */

namespace Sabatier\Foundation\Predicates;

use Override;
use Sabatier\Foundation\Sequence;
use function Sabatier\Foundation\fatal_error;
use function Sabatier\Foundation\in_string;
use function Sabatier\Foundation\string_is_equal;
use function Sabatier\Foundation\typeof;

/** @internal */
class InPredicateOperator extends PredicateOperator
{
    #[Override]
    public function performPrimitiveOperation(mixed $left, mixed $right): bool
    {
        $options = $this->compareOptions();
        if (is_string($left) && is_string($right)) {
            return in_string($right, $left, $options);
        } elseif ($right instanceof Sequence) {
            return $right->contains(fn(string $string): bool => string_is_equal($string, $left, $options));
        }
        fatal_error(sprintf("Invalid argument: expecting \"string, %s\", \"%s\" given", Sequence::class, typeof($right)));
    }
}
