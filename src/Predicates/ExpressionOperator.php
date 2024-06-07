<?php

namespace Sabatier\Foundation\Predicates;

use Sabatier\Foundation\ArrayClass;
use Sabatier\Foundation\Dictionary;

/** @internal */
class ExpressionOperator extends Expression
{
    public readonly string $operatorSymbol;
    public readonly bool $isDeterministic;

    /**
     * @param string $name
     * @param ArrayClass<Expression>|null $arguments
     * @param ExpressionOperatorType $operatorType
     */
    public function __construct(public readonly string $name, public readonly ?ArrayClass $arguments, public readonly ExpressionOperatorType $operatorType)
    {
        parent::__construct(ExpressionType::operator);
        unset($this->operatorSymbol);
        unset($this->isDeterministic);
    }

    public function __get(string $name)
    {
        if ($name === "operatorSymbol") {
            $this->$name = match ($this->operatorType) {
                ExpressionOperatorType::addTo => ExpressionOperatorSymbol::addition,
                ExpressionOperatorType::fromSubtract => ExpressionOperatorSymbol::subtraction,
                ExpressionOperatorType::multiplyBy => ExpressionOperatorSymbol::multiplication,
                ExpressionOperatorType::divideBy => ExpressionOperatorSymbol::division,
                ExpressionOperatorType::modulusBy => ExpressionOperatorSymbol::modulo,
                ExpressionOperatorType::raiseToPower => ExpressionOperatorSymbol::raiseToPower,
                ExpressionOperatorType::bitwiseAndWith => ExpressionOperatorSymbol::bitwiseAnd,
                ExpressionOperatorType::bitwiseOrWith => ExpressionOperatorSymbol::bitwiseOr,
                ExpressionOperatorType::bitwiseXorWith => ExpressionOperatorSymbol::bitwiseXor,
                ExpressionOperatorType::leftshiftBy => ExpressionOperatorSymbol::shiftLeft,
                ExpressionOperatorType::rightshiftBy => ExpressionOperatorSymbol::shiftRight,
                default => $this->operatorType->name
            };
            return $this->$name;
        } elseif ($name === "isDeterministic") {
            $this->$name = match ($this->operatorType) {
                ExpressionOperatorType::average, ExpressionOperatorType::sum, ExpressionOperatorType::count, ExpressionOperatorType::min, ExpressionOperatorType::max, ExpressionOperatorType::stddev, ExpressionOperatorType::addTo, ExpressionOperatorType::fromSubtract, ExpressionOperatorType::multiplyBy, ExpressionOperatorType::divideBy, ExpressionOperatorType::modulusBy, ExpressionOperatorType::sqrt, ExpressionOperatorType::ln, ExpressionOperatorType::log, ExpressionOperatorType::raiseToPower, ExpressionOperatorType::exp, ExpressionOperatorType::ceiling, ExpressionOperatorType::abs, ExpressionOperatorType::trunc, ExpressionOperatorType::floor, ExpressionOperatorType::uppercase, ExpressionOperatorType::lowercase, ExpressionOperatorType::bitwiseAndWith, ExpressionOperatorType::bitwiseOrWith, ExpressionOperatorType::bitwiseXorWith, ExpressionOperatorType::leftshiftBy, ExpressionOperatorType::rightshiftBy, ExpressionOperatorType::index, ExpressionOperatorType::indexFirst, ExpressionOperatorType::indexLast, ExpressionOperatorType::indexSize, ExpressionOperatorType::year, ExpressionOperatorType::month, ExpressionOperatorType::week, ExpressionOperatorType::day, ExpressionOperatorType::hour, ExpressionOperatorType::minute, ExpressionOperatorType::second, ExpressionOperatorType::concat, ExpressionOperatorType::isNull, ExpressionOperatorType::ifNull, ExpressionOperatorType::nullIf => true,
                default => false
            };
            return $this->$name;
        } else {
            return parent::__get($name);
        }
    }

    public static function operatorWithName(string $name, ?ArrayClass $arguments = null): Expression
    {
        return new ExpressionOperator($name, $arguments, ExpressionOperatorType::fromFunctionName($name));
    }

    public function expressionValue(mixed $object = null, ?Dictionary $context = null): mixed
    {
        $selector = $this->operatorType->name;
        $arguments = $this->arguments?->map(fn(Expression $expression): mixed => $expression->expressionValue($object, $context)) ?? new ArrayClass();
        return PredicateUtilities::$selector(...$arguments);
    }

    public function function (): string
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
        switch ($this->operatorType) {
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
                $format = $arguments->join(" $this->operatorSymbol ");
                break;
            default:
                $format = $this->function();
                $format .= "(";
                $format .= $arguments->join(", ");
                $format .= ")";
                break;
        }
        return $format;
    }
}
