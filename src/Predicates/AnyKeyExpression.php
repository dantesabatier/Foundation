<?php

namespace Sabatier\Foundation\Predicates;

use Override;
use Sabatier\Foundation\Dictionary;
use function Sabatier\Foundation\fatal_error;

/** @internal */
class AnyKeyExpression extends Expression
{
    private static ?AnyKeyExpression $default = null;
    public string $predicateFormat {
        get => "ANYKEY";
    }

    public function __construct()
    {
        parent::__construct(ExpressionType::anyKey);
    }

    public static function default(): AnyKeyExpression
    {
        AnyKeyExpression::$default ??= new AnyKeyExpression();
        return AnyKeyExpression::$default;
    }

    /** @noinspection PhpMixedReturnTypeCanBeReducedInspection */
    #[Override]
    public function expressionValue(mixed $object = null, ?Dictionary $context = null): mixed
    {
        fatal_error("Cannot evaluate any key expression");
    }
}
