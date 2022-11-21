<?php

namespace Sabatier\Foundation\Predicates;

use LogicException;
use Sabatier\Foundation\Dictionary;

/** @internal */
class AnyKeyExpression extends Expression
{
    private static ?AnyKeyExpression $default = null;

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
