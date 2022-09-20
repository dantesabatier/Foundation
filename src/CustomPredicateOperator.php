<?php
/**
 * Created by PhpStorm.
 * User: dante
 * Date: 24/07/20
 * Time: 11:44
 */

namespace Sabatier\Foundation;

use InvalidArgumentException;

/** @internal */
class CustomPredicateOperator extends PredicateOperator
{
    public function __construct(public readonly string $selector)
    {
        parent::__construct(PredicateOperatorType::customSelector);
    }

    public function performPrimitiveOperation(mixed $left, mixed $right): bool
    {
        if (!is_object($left)) {
            throw new InvalidArgumentException(sprintf("invalid argument: expecting \"object\", \"%s\" given", typeof($left)));
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
