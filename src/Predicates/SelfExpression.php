<?php

namespace Sabatier\Foundation\Predicates;

use Override;
use Sabatier\Foundation\Dictionary;

/** @internal */
final class SelfExpression extends Expression
{
    public string $predicateFormat {
        get => "SELF";
    }

    public function __construct()
    {
        parent::__construct(ExpressionType::evaluatedObject);
    }

    #[Override]
    public function expressionValue(mixed $object = null, ?Dictionary $context = null): mixed
    {
        return $object;
    }
}
