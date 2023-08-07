<?php

namespace Sabatier\Foundation\Predicates;

use JetBrains\PhpStorm\Pure;
use Sabatier\Foundation\Dictionary;
use function Sabatier\Foundation\human_readable_value;

/** @internal */
class VariableExpression extends Expression
{
    #[Pure]
    public function __construct(private readonly string $variable)
    {
        parent::__construct(ExpressionType::variable);
    }

    public function withSubstitutionVariables(Dictionary $variables): Expression
    {
        $value = $variables[$this->variable()];
        if (!$value instanceof Expression) {
            return Expression::expressionForConstantValue($value);
        }
        return $value;
    }

    public function expressionValue(mixed $object = null, ?Dictionary $context = null): mixed
    {
        $value = $this->withSubstitutionVariables($context ?? new Dictionary())->expressionValue($object, $context);
        if (Predicate::$debugDefault) {
            error_log(sprintf("Foundation: expression %s: %s", $this->expressionType->name, human_readable_value($value)));
        }
        return $value;
    }

    public function keyPath(): string
    {
        return $this->variable;
    }

    public function variable(): string
    {
        return $this->variable;
    }

    public function operand(): ?Expression
    {
        return $this;
    }

    #[Pure]
    public function predicateFormat(): string
    {
        return $this->variable();
    }
}
