<?php

namespace Sabatier\Foundation\Predicates;

use Sabatier\Foundation\ArrayClass;
use Sabatier\Foundation\Dictionary;

/** @internal */
class ExpressionOperator extends Expression
{
    /**
     * @param string $name
     * @param ArrayClass<Expression>|null $arguments
     * @param ExpressionOperatorType $operatorType
     */
    public function __construct(private readonly string $name, private readonly ?ArrayClass $arguments, private readonly ExpressionOperatorType $operatorType)
    {
        parent::__construct(ExpressionType::operator);
    }

    public static function operatorWithName(string $name, ?ArrayClass $arguments = null): Expression
    {
        return new ExpressionOperator($name, $arguments, ExpressionOperatorType::operatorType($name));
    }

    public function expressionValue(mixed $object = null, ?Dictionary $context = null): mixed
    {
        $selector = $this->operatorType->name;
        $arguments = $this->arguments?->map(fn(Expression $expression): mixed => $expression->expressionValue($object, $context)) ?? new ArrayClass();
        return PredicateUtilities::$selector(...$arguments);
    }

    public function operatorType(): ExpressionOperatorType
    {
        return $this->operatorType;
    }

    /** @noinspection PhpPureAttributeCanBeAddedInspection */
    public function operatorSymbol(): ?string
    {
        return ExpressionOperatorType::symbol($this->operatorType());
    }

    public function function(): string
    {
        return $this->name;
    }

    public function arguments(): ?ArrayClass
    {
        return $this->arguments;
    }

    public function predicateFormat(): string
    {
        $arguments = $this->arguments?->compactMap(function (Expression $expression): ?string {
            $format = $expression->predicateFormat();
            $operand = $expression->operand();
            if ($operand instanceof ExpressionOperator) {
                $format = $operand->predicateFormat();
            } elseif ($expression instanceof ExpressionOperator) {
                $format = "($format)";
            }
            if (empty($format)) {
                return null;
            }
            return $format;
        }) ?? new ArrayClass();
        switch ($this->operatorType()) {
            case ExpressionOperatorType::addTo:
            case ExpressionOperatorType::fromSubtract:
            case ExpressionOperatorType::multiplyBy:
            case ExpressionOperatorType::divideBy:
            case ExpressionOperatorType::modulusBy:
            case ExpressionOperatorType::bitwiseAndWith:
            case ExpressionOperatorType::bitwiseOrWith:
            case ExpressionOperatorType::bitwiseXorWith:
            case ExpressionOperatorType::leftshiftBy:
            case ExpressionOperatorType::rightshiftBy:
                $format = $arguments->join(" {$this->operatorSymbol()} ");
                break;
            default:
                $format = $this->function();
                $format .= '(';
                $format .= $arguments->join(', ');
                $format .= ')';
                break;
        }
        return $format;
    }
}
