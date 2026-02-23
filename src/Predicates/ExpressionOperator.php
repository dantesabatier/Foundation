<?php

namespace Sabatier\Foundation\Predicates;

use Override;
use Sabatier\Foundation\ArrayClass;
use Sabatier\Foundation\Dictionary;

/** @internal */
final class ExpressionOperator extends Expression
{
    public string $operatorSymbol {
        get => match ($this->operatorType) {
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
    }
    public bool $isDeterministic {
        get => match ($this->operatorType) {
            ExpressionOperatorType::average,
            ExpressionOperatorType::sum,
            ExpressionOperatorType::count,
            ExpressionOperatorType::min,
            ExpressionOperatorType::max,
            ExpressionOperatorType::median,
            ExpressionOperatorType::mode,
            ExpressionOperatorType::stddev,
            ExpressionOperatorType::addTo,
            ExpressionOperatorType::fromSubtract,
            ExpressionOperatorType::multiplyBy,
            ExpressionOperatorType::divideBy,
            ExpressionOperatorType::modulusBy,
            ExpressionOperatorType::sqrt,
            ExpressionOperatorType::ln,
            ExpressionOperatorType::log,
            ExpressionOperatorType::raiseToPower,
            ExpressionOperatorType::exp,
            ExpressionOperatorType::ceiling,
            ExpressionOperatorType::abs,
            ExpressionOperatorType::trunc,
            ExpressionOperatorType::floor,
            ExpressionOperatorType::chs,
            ExpressionOperatorType::uppercase,
            ExpressionOperatorType::lowercase,
            ExpressionOperatorType::canonical,
            ExpressionOperatorType::concat,
            ExpressionOperatorType::substring,
            ExpressionOperatorType::replace,
            ExpressionOperatorType::regexpReplace,
            ExpressionOperatorType::length,
            ExpressionOperatorType::trim,
            ExpressionOperatorType::bitwiseAndWith,
            ExpressionOperatorType::bitwiseOrWith,
            ExpressionOperatorType::bitwiseXorWith,
            ExpressionOperatorType::leftshiftBy,
            ExpressionOperatorType::rightshiftBy,
            ExpressionOperatorType::onesComplement,
            ExpressionOperatorType::index,
            ExpressionOperatorType::indexFirst,
            ExpressionOperatorType::indexLast,
            ExpressionOperatorType::indexSize,
            ExpressionOperatorType::year,
            ExpressionOperatorType::month,
            ExpressionOperatorType::week,
            ExpressionOperatorType::day,
            ExpressionOperatorType::hour,
            ExpressionOperatorType::minute,
            ExpressionOperatorType::second,
            ExpressionOperatorType::date,
            ExpressionOperatorType::dateFormat,
            ExpressionOperatorType::dateDiff,
            ExpressionOperatorType::isNull,
            ExpressionOperatorType::ifNull,
            ExpressionOperatorType::nullIf,
            ExpressionOperatorType::cast
            => true,
            default => false
        };
    }
    #[\Override]
    public string $predicateFormat {
        get {
            $arguments = $this->arguments?->compactMap(function (Expression $expression): ?string {
                $format = $expression->predicateFormat;
                $operand = $expression->operand;
                if ($operand instanceof ExpressionOperator) {
                    $format = $operand->predicateFormat;
                } elseif ($expression instanceof ExpressionOperator) {
                    $format = "($format)";
                }
                if (empty($format)) {
                    return null;
                }
                return $format;
            }) ?? new ArrayClass();
            return match ($this->operatorType) {
                ExpressionOperatorType::addTo, ExpressionOperatorType::fromSubtract, ExpressionOperatorType::multiplyBy, ExpressionOperatorType::divideBy, ExpressionOperatorType::modulusBy, ExpressionOperatorType::bitwiseAndWith, ExpressionOperatorType::bitwiseOrWith, ExpressionOperatorType::bitwiseXorWith, ExpressionOperatorType::leftshiftBy, ExpressionOperatorType::rightshiftBy => $arguments->join(" $this->operatorSymbol "),
                default => "$this->function({$arguments->join(", ")})",
            };
        }
    }
    public string $name;
    public ExpressionOperatorType $operatorType;

    /**
     * @param string $name
     * @param ArrayClass<Expression>|null $arguments
     * @param ExpressionOperatorType $operatorType
     */
    public function __construct(string $name, ?ArrayClass $arguments, ExpressionOperatorType $operatorType)
    {
        parent::__construct(ExpressionType::operator);
        $this->name = $name;
        $this->arguments = $arguments;
        $this->operatorType = $operatorType;
        $this->function = $name;
    }

    public static function operatorWithName(string $name, ?ArrayClass $arguments = null): Expression
    {
        return new ExpressionOperator($name, $arguments, ExpressionOperatorType::fromFunctionName($name));
    }

    #[Override]
    public function expressionValue(mixed $object = null, ?Dictionary $context = null): mixed
    {
        $selector = $this->operatorType->name;
        $arguments = $this->arguments?->map(fn(Expression $expression): mixed => $expression->expressionValue($object, $context)) ?? new ArrayClass();
        return PredicateUtilities::$selector(...$arguments);
    }
}
