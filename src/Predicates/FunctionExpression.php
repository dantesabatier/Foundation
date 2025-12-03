<?php

namespace Sabatier\Foundation\Predicates;

use JetBrains\PhpStorm\ExpectedValues;
use Override;
use Sabatier\Foundation\ArrayClass;
use Sabatier\Foundation\Dictionary;
use function Sabatier\Foundation\human_readable_value;
use function Sabatier\Foundation\typeof;

/** @internal */
class FunctionExpression extends Expression
{
    public string $predicateFormat {
        get {
            $format = "";
            $operand = $this->operand;
            if ($operand instanceof ExpressionOperator) {
                $format .= $operand->function;
                $format .= "(";
            } else {
                $format .= "FUNCTION";
                $format .= "(";
                $format .= $operand->description;
                $format .= ", ";
                if ($selector = $this->selector) {
                    $format .= $selector;
                    if (!$this->arguments?->isEmpty) {
                        $format .= ", ";
                    }
                }
            }
            $format .= $this->arguments?->join(", ") ?? "";
            return $format . ")";
        }
    }
    public string $selector;

    /**
     * @param ExpressionType $expressionType
     * @param Expression $operand
     * @param string $selector
     * @param ArrayClass<Expression>|null $arguments
     */
    public function __construct(ExpressionType $expressionType, Expression $operand, string $selector, ?ArrayClass $arguments = null)
    {
        parent::__construct($expressionType);
        $this->arguments = $arguments;
        $this->operand = $operand;
        $this->selector = $selector;
        $this->function = $selector;
    }

    public static function functionWithSelector(Expression $target, string $selector, ?ArrayClass $arguments = null): Expression
    {
        return new FunctionExpression(ExpressionType::function, $target, $selector, $arguments);
    }

    public static function functionWithName(string $name, ?ArrayClass $arguments = null): Expression
    {
        return FunctionExpression::functionWithSelector(ExpressionOperator::operatorWithName($name, $arguments), $name, $arguments);
    }

    #[Override]
    public function withSubstitutionVariables(Dictionary $variables): Expression
    {
        return FunctionExpression::functionWithSelector($this->operand->withSubstitutionVariables($variables), $this->selector, $this->arguments?->map(fn(Expression $expression): Expression => $expression->withSubstitutionVariables($variables)));
    }

    #[Override]
    public function expressionValue(mixed $object = null, ?Dictionary $context = null): mixed
    {
        $selector = $this->selector;
        $operand = $this->operand;
        /** @var ArrayClass<Expression> $arguments */
        $arguments = $this->arguments ?? new ArrayClass();
        if ($operand instanceof ExpressionOperator) {
            $value = $operand->expressionValue($object, $context);
            if (Predicate::$debugDefault) {
                error_log(sprintf("Foundation: expression %s: %s(%s) => %s", $this->expressionType->name, $selector, $arguments->join(", "), human_readable_value($value)));
            }
            return $value;
        }
        $obj = $operand->expressionValue($object, $context);
        $arguments = $arguments->map(fn(Expression $expression): mixed => $expression->expressionValue($object, $context));
        $value = $obj->$selector(...$arguments);
        if (Predicate::$debugDefault) {
            error_log(sprintf("Foundation: expression %s: %s::%s(%s) => %s", $this->expressionType->name, typeof($obj), $selector, $arguments->join(", "), human_readable_value($value)));
        }
        return $value;
    }

    #[Override]
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
            foreach ($arguments as $argument) {
                $argument->accept($visitor, $flags);
            }
        }
    }
}
