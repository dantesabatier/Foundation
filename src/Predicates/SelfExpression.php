<?php

namespace Sabatier\Foundation\Predicates;

use Override;
use Sabatier\Foundation\Dictionary;

/** @internal */
class SelfExpression extends Expression
{
    public function __construct()
    {
        parent::__construct(ExpressionType::evaluatedObject);
    }

    #[Override]
    public function expressionValue(mixed $object = null, ?Dictionary $context = null): mixed
    {
        return $object;
    }

    #[Override]
    public function predicateFormat(): string
    {
        return "SELF";
    }
}
