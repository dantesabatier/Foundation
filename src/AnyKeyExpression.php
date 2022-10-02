<?php

namespace Sabatier\Foundation;

use JetBrains\PhpStorm\Pure;
use LogicException;

/** @internal */
class AnyKeyExpression extends Expression
{
    private static ?AnyKeyExpression $default = null;

    #[Pure]
    public function __construct()
    {
        parent::__construct(ExpressionType::anyKey);
    }

    public static function default(): AnyKeyExpression
    {
        if (AnyKeyExpression::$default === null) {
            AnyKeyExpression::$default = new AnyKeyExpression();
        }
        return AnyKeyExpression::$default;
    }

    public function expressionValue(mixed $object = null, ?Dictionary $context = null): mixed
    {
        throw new LogicException('Cannot evaluate any key expression');
    }

    public function predicateFormat(): string
    {
        return 'ANYKEY';
    }
}
