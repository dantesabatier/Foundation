<?php

/**
 * @author Dante Sabatier <dantesabatier@me.com>
 * @version 1.0
 * @package Sabatier\Foundation
 */

namespace Sabatier\Foundation;

use JetBrains\PhpStorm\ExpectedValues;

/** @internal */
class FunctionExpression extends Expression
{
    public function __construct(ExpressionType $expressionType, public readonly Expression $operand, public readonly string $selector, public readonly ?ArrayClass $arguments = null)
    {
        parent::__construct($expressionType);
    }

    public static function functionWithSelector(Expression $target, string $selector, ?ArrayClass $arguments = null): Expression
    {
        return new FunctionExpression(ExpressionType::function, $target, $selector, $arguments);
    }

    public static function functionWithName(string $name, ?ArrayClass $arguments = null): Expression
    {
        return FunctionExpression::functionWithSelector(ExpressionOperator::operatorWithName($name, $arguments), $name, $arguments);
    }

    public function withSubstitutionVariables(Dictionary $variables): Expression
    {
        return FunctionExpression::functionWithSelector($this->operand->withSubstitutionVariables($variables), $this->selector, $this->arguments?->map(fn(Expression $expression): Expression => $expression->withSubstitutionVariables($variables)));
    }

    public function expressionValue(mixed $object = null, ?Dictionary $context = null): mixed
    {
        $selector = $this->selector;
        $operand = $this->operand;
        $arguments = $this->arguments ?? new ArrayClass();
        if ($operand instanceof ExpressionOperator) {
            $value = $operand->expressionValue($object, $context);
            if (Predicate::$debugLevel) {
                error_log(sprintf("Foundation: expression %s: %s(%s) => %s", $this->expressionType->name, $selector, $arguments->join(", "), human_readable_value($value)));
            }
            return $value;
        }
        $obj = $operand->expressionValue($object, $context);
        $arguments = $arguments->map(fn(Expression $expression): mixed => $expression->expressionValue($object, $context));
        $value = $obj->$selector(...$arguments);
        if (Predicate::$debugLevel) {
            error_log(sprintf("Foundation: expression %s: %s::%s(%s) => %s", $this->expressionType->name, typeof($obj), $selector, $arguments->join(", "), human_readable_value($value)));
        }
        return $value;
    }

    public function accept(PredicateVisitor $visitor, #[ExpectedValues(flagsFromClass: PredicateVisitorFlags::class)] int $flags): void
    {
        if (!($flags & PredicateVisitorFlags::expressions)) {
            return;
        }
        if ($flags & PredicateVisitorFlags::internalNodes) {
            $visitor->visitPredicateExpression($this);
        }
        $this->operand->accept($visitor, $flags);
        if ($arguments = $this->arguments) {
            foreach ($arguments as $expression) {
                $expression->accept($visitor, $flags);
            }
        }
        if ($flags & PredicateVisitorFlags::internalNodes) {
            $visitor->visitPredicateExpression($this);
        }
    }

    public function function(): string
    {
        return $this->selector;
    }

    public function arguments(): ?ArrayClass
    {
        return $this->arguments;
    }

    public function operand(): ?Expression
    {
        return $this->operand;
    }

    public function predicateFormat(): string
    {
        $format = '';
        $operand = $this->operand;
        if ($operand instanceof ExpressionOperator) {
            $format .= $operand->function();
            $format .= '(';
        } else {
            $format .= "FUNCTION";
            $format .= '(';
            $format .= $operand->description();
            $format .= ', ';
            if ($selector = $this->selector) {
                $format .= $selector;
                if (!$this->arguments?->isEmpty()) {
                    $format .= ', ';
                }
            }
        }
        $format .= $this->arguments?->join(', ') ?? '';
        $format .= ')';
        return $format;
    }
}
