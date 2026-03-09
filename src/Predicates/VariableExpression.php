<?php

namespace Sabatier\Foundation\Predicates;

use Override;
use Sabatier\Foundation\Dictionary;
use function Sabatier\Foundation\human_readable_value;

/** @internal */
final class VariableExpression extends Expression
{
    #[Override]
    public string $predicateFormat {
        get => $this->variable;
    }

    public function __construct(string $variable)
    {
        parent::__construct(ExpressionType::variable);
        $this->variable = $variable;
        $this->keyPath = $variable;
        $this->operand = $this;
    }

    #[Override]
    public function withSubstitutionVariables(Dictionary $variables): Expression
    {
        $value = $variables[$this->variable];
        if (!$value instanceof Expression) {
            return Expression::expressionForConstantValue($value);
        }
        return $value;
    }

    #[Override]
    public function expressionValue(mixed $object = null, ?Dictionary $context = null): mixed
    {
        $value = $this->withSubstitutionVariables($context ?? new Dictionary())->expressionValue($object, $context);
        if (Predicate::$debugDefault) {
            $value
                |> human_readable_value(...)
                |> (fn(string $x): string => sprintf("Foundation: %s %s: %s", $this->debugDescription, $this->expressionType->name, $x))
                |> error_log(...);
        }
        return $value;
    }
}
