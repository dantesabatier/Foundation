<?php

/**
 * Created by PhpStorm.
 * User: dante
 * Date: 24/07/20
 * Time: 09:40
 */

namespace Sabatier\Foundation\Predicates;

use JetBrains\PhpStorm\ExpectedValues;
use Sabatier\Foundation\ArrayClass;
use Sabatier\Foundation\CompareOptions;
use Sabatier\Foundation\ObjectClass;
use Sabatier\Foundation\Set;
use function Sabatier\Foundation\fatal_error;
use function Sabatier\Foundation\human_readable_value;
use function Sabatier\Foundation\request_concrete_implementation;
use function Sabatier\Foundation\typeof;

/** @internal */
class PredicateOperator extends ObjectClass
{
    public string $symbol {
        get => match ($this->operatorType) {
            PredicateOperatorType::lessThan => PredicateOperatorSymbol::lessThan,
            PredicateOperatorType::lessThanOrEqualTo => PredicateOperatorSymbol::lessThanOrEqualTo,
            PredicateOperatorType::greaterThan => PredicateOperatorSymbol::greaterThan,
            PredicateOperatorType::greaterThanOrEqualTo => PredicateOperatorSymbol::greaterThanOrEqualTo,
            PredicateOperatorType::equalTo => PredicateOperatorSymbol::equalTo,
            PredicateOperatorType::notEqualTo => PredicateOperatorSymbol::notEqualTo,
            PredicateOperatorType::like => PredicateOperatorSymbol::like,
            PredicateOperatorType::matches => PredicateOperatorSymbol::matches,
            PredicateOperatorType::beginsWith => PredicateOperatorSymbol::beginsWith,
            PredicateOperatorType::endsWith => PredicateOperatorSymbol::endsWith,
            PredicateOperatorType::contains => PredicateOperatorSymbol::contains,
            PredicateOperatorType::in => PredicateOperatorSymbol::in,
            PredicateOperatorType::between => PredicateOperatorSymbol::between,
            default => fatal_error()
        };
    }
    public string $predicateFormat {
        get => $this->symbol;
    }
    public string $description {
        get => $this->predicateFormat;
    }
    #[ExpectedValues(flagsFromClass: CompareOptions::class)]
    public int $compareOptions {
        get => $this->options;
    }

    public function __construct(public readonly PredicateOperatorType $operatorType, public readonly ComparisonPredicateModifier $modifier = ComparisonPredicateModifier::direct, #[ExpectedValues(flagsFromClass: ComparisonPredicateOptions::class)] public readonly int $options = ComparisonPredicateOptions::none)
    {
    }

    public static function newOperator(PredicateOperatorType $type, ComparisonPredicateModifier $modifier = ComparisonPredicateModifier::direct, #[ExpectedValues(flagsFromClass: ComparisonPredicateOptions::class)] int $options = ComparisonPredicateOptions::none): PredicateOperator
    {
        return match ($type) {
            PredicateOperatorType::lessThan, PredicateOperatorType::lessThanOrEqualTo, PredicateOperatorType::greaterThan, PredicateOperatorType::greaterThanOrEqualTo => new ComparisonPredicateOperator($type, $modifier, $options, $type),
            PredicateOperatorType::equalTo => new EqualityPredicateOperator($type, $modifier, $options),
            PredicateOperatorType::notEqualTo => new EqualityPredicateOperator($type, $modifier, $options, true),
            PredicateOperatorType::matches => new MatchingPredicateOperator($type, $modifier, $options),
            PredicateOperatorType::like => new LikePredicateOperator($type, $modifier, $options),
            PredicateOperatorType::beginsWith => new SubstringPredicateOperator($type, $modifier, $options, SubstringPredicateOperatorPosition::beginsWith),
            PredicateOperatorType::endsWith => new SubstringPredicateOperator($type, $modifier, $options, SubstringPredicateOperatorPosition::endsWith),
            PredicateOperatorType::contains => new SubstringPredicateOperator($type, $modifier, $options, SubstringPredicateOperatorPosition::contains),
            PredicateOperatorType::in => new InPredicateOperator($type, $modifier, $options),
            PredicateOperatorType::between => new BetweenPredicateOperator($type, $modifier, $options),
            default => fatal_error("Invalid argument: $type->name"),
        };
    }

    public function performOperation(mixed $left, mixed $right): bool
    {
        $f = function () use ($left, $right): bool {
            if ($this->modifier === ComparisonPredicateModifier::direct) {
                return $this->performPrimitiveOperation($left, $right);
            }
            if ($left === null) {
                return match ($this->modifier) {
                    ComparisonPredicateModifier::all => true,
                    default => false,
                };
            }
            if (!$left instanceof ArrayClass && !$left instanceof Set) {
                fatal_error(sprintf("Invalid argument: the left hand side for an ALL or ANY modifier must be an %s or a %s, \"%s\" given", ArrayClass::class, Set::class, typeof($left)));
            }
            if ($left->isEmpty) {
                return false;
            }
            $predicate = fn(mixed $e): bool => $this->performPrimitiveOperation($e, $right);
            return match ($this->modifier) {
                ComparisonPredicateModifier::all => $left->allSatisfy($predicate),
                default => $left->contains($predicate),
            };
        };
        $v = $f();
        if (Predicate::$debugDefault) {
            error_log(sprintf("Foundation: predicate operator %s (%s): (%s)%s %s (%s)%s => %s", $this->operatorType->name, $this->modifier->name, typeof($left), human_readable_value($left), $this->symbol, typeof($right), human_readable_value($right), human_readable_value($v)));
        }
        return $v;
    }

    public function performPrimitiveOperation(mixed $left, mixed $right): bool
    {
        request_concrete_implementation($this, __FUNCTION__);
    }

    public function accept(PredicateVisitor $visitor, #[ExpectedValues(flagsFromClass: PredicateVisitorFlags::class)] int $flags): void
    {
        if ($flags & PredicateVisitorFlags::operators) {
            $visitor->visitPredicateOperator($this);
        }
    }
}
