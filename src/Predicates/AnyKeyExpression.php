<?php

declare(strict_types=1);

namespace Sabatier\Foundation\Predicates;

use Override;
use Sabatier\Foundation\Dictionary;
use function Sabatier\Foundation\fatal_error;

/** @internal */
final class AnyKeyExpression extends Expression
{
    private static ?AnyKeyExpression $default = null;
    #[Override]
    public string $predicateFormat {
        get => "ANYKEY";
    }

    public function __construct()
    {
        parent::__construct(ExpressionType::anyKey);
    }

    public static function default(): AnyKeyExpression
    {
        return self::$default ??= new AnyKeyExpression();
    }

    /** @noinspection PhpMixedReturnTypeCanBeReducedInspection */
    #[Override]
    public function expressionValue(mixed $object = null, ?Dictionary $context = null): mixed
    {
        fatal_error("Cannot evaluate any key expression");
    }
}
