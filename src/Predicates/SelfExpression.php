<?php

namespace Sabatier\Foundation\Predicates;

use Sabatier\Foundation\Dictionary;

/** @internal */
class SelfExpression extends Expression
{
    public function __construct()
    {
        parent::__construct(ExpressionType::evaluatedObject);
    }

    public function expressionValue(mixed $object = null, ?Dictionary $context = null): mixed
    {
        return $object;
    }

    public function predicateFormat(): string
    {
        return "SELF";
    }
}
