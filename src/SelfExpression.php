<?php

/**
 * @author Dante Sabatier <dantesabatier@me.com>
 * @version 1.0
 * @package Sabatier\Foundation
 */

namespace Sabatier\Foundation;

use JetBrains\PhpStorm\Pure;

/** @internal */
class SelfExpression extends Expression
{
    #[Pure]
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
