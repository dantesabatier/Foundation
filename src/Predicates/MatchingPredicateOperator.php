<?php

declare(strict_types=1);

/**
 * Created by PhpStorm.
 * User: dante
 * Date: 24/07/20
 * Time: 11:45
 */
namespace Sabatier\Foundation\Predicates;

use Override;
use Sabatier\Foundation\CompareOptions;
use function Sabatier\Foundation\human_readable_value;
use function Sabatier\Foundation\string_matches;

/** @internal */
class MatchingPredicateOperator extends StringPredicateOperator
{
    #[Override]
    protected function performPrimitiveOperation(mixed $left, mixed $right): bool
    {
        if ($left === null || $right === null) {
            return false;
        }
        assert(is_string($left) && is_string($right), sprintf("Cannot perform substring check on non-strings %s and %s", human_readable_value($left), human_readable_value($right)));
        $options = $this->compareOptions;
        if (($options & CompareOptions::quoted) === 0) {
            $options |= CompareOptions::quoted;
        }
        return string_matches($left, $right, $options);
    }
}
