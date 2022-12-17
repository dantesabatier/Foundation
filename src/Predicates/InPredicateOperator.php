<?php

/**
 * Created by PhpStorm.
 * User: dante
 * Date: 24/07/20
 * Time: 11:45
 */

namespace Sabatier\Foundation\Predicates;

use InvalidArgumentException;
use Sabatier\Foundation\Sequence;
use function Sabatier\Foundation\human_readable_value;
use function Sabatier\Foundation\in_string;
use function Sabatier\Foundation\string_is_equal;
use function Sabatier\Foundation\typeof;

/** @internal */
class InPredicateOperator extends PredicateOperator
{
    public function performPrimitiveOperation(mixed $left, mixed $right): bool
    {
        if (Predicate::$debugDefault) {
            error_log(sprintf("Foundation: predicate operator %s: (%s)%s %s (%s)%s", $this->operatorType->name, typeof($left), human_readable_value($left), $this->symbol(), typeof($right), human_readable_value($right)));
        }
        $options = $this->compareOptions();
        if (is_string($left) && is_string($right)) {
            return in_string($right, $left, $options);
        } elseif ($right instanceof Sequence) {
            return $right->contains(fn(string $string): bool => string_is_equal($string, $left, $options));
        }
        throw new InvalidArgumentException(sprintf("Invalid argument: expecting \"string, %s\", \"%s\" given", Sequence::class, typeof($right)));
    }
}
