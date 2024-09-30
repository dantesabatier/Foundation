<?php

/**
 * Created by PhpStorm.
 * User: dante
 * Date: 24/07/20
 * Time: 11:44
 */

namespace Sabatier\Foundation\Predicates;

use Override;
use function Sabatier\Foundation\fatal_error;
use function Sabatier\Foundation\typeof;

/** @internal */
class CustomPredicateOperator extends PredicateOperator
{
    public function __construct(public readonly string $selector)
    {
        parent::__construct(PredicateOperatorType::customSelector);
    }

    #[Override]
    public function performPrimitiveOperation(mixed $left, mixed $right): bool
    {
        if (!is_object($left)) {
            fatal_error(sprintf("Invalid argument: expecting \"object\", \"%s\" given", typeof($left)));
        }
        $selector = $this->selector;
        $arguments = [$right];
        return $left->$selector(...$arguments);
    }

    #[Override]
    public function symbol(): string
    {
        return $this->selector;
    }
}
