<?php

/**
 * Created by PhpStorm.
 * User: dante
 * Date: 24/07/20
 * Time: 11:44
 */

namespace Sabatier\Foundation\Predicates;

use InvalidArgumentException;
use function Sabatier\Foundation\human_readable_value;
use function Sabatier\Foundation\typeof;

/** @internal */
class CustomPredicateOperator extends PredicateOperator
{
    public function __construct(public readonly string $selector)
    {
        parent::__construct(PredicateOperatorType::customSelector);
    }

    public function performPrimitiveOperation(mixed $left, mixed $right): bool
    {
        if (Predicate::$debugDefault) {
            error_log(sprintf("Foundation: predicate operator %s: (%s)%s %s (%s)%s", $this->operatorType->name, typeof($left), human_readable_value($left), $this->symbol(), typeof($right), human_readable_value($right)));
        }
        if (!is_object($left)) {
            throw new InvalidArgumentException(sprintf("Invalid argument: expecting \"object\", \"%s\" given", typeof($left)));
        }
        $selector = $this->selector;
        $arguments = [$right];
        return $left->$selector(...$arguments);
    }

    public function symbol(): string
    {
        return $this->selector;
    }
}
