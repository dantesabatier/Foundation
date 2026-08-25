<?php

/**
 * Created by PhpStorm.
 * User: dante
 * Date: 24/07/20
 * Time: 11:45
 */

declare(strict_types=1);

namespace Sabatier\Foundation\Predicates;

use Override;
use Sabatier\Foundation\Sequence;
use function Sabatier\Foundation\fatal_error;
use function Sabatier\Foundation\in_string;
use function Sabatier\Foundation\is_equal;
use function Sabatier\Foundation\string_is_equal;
use function Sabatier\Foundation\typeof;

/** @internal */
final class InPredicateOperator extends PredicateOperator
{
    #[Override]
    protected function performPrimitiveOperation(mixed $left, mixed $right): bool
    {
        $options = $this->compareOptions;
        if (is_string($left) && is_string($right)) {
            return in_string($right, $left, $options);
        }
        if ($right instanceof Sequence) {
            // The collection is not necessarily made of strings — "n IN {1,5,9}" hands over numbers — so compare through is_equal() and fall back to the string comparison only when both sides are strings and the options matter.
            return $right->contains(fn(mixed $element): bool => (is_string($element) && is_string($left)) ? string_is_equal($element, $left, $options) : is_equal($element, $left));
        }
        fatal_error(sprintf("Invalid argument: expecting \"string, %s\", \"%s\" given", Sequence::class, typeof($right)));
    }
}
