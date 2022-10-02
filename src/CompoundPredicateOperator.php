<?php

/**
 * Created by PhpStorm.
 * User: dante
 * Date: 24/07/20
 * Time: 11:43
 */

namespace Sabatier\Foundation;

use JetBrains\PhpStorm\ExpectedValues;

/** @internal */
class CompoundPredicateOperator extends PredicateOperator
{
    private static ?CompoundPredicateOperator $notPredicateOperator = null;
    private static ?CompoundPredicateOperator $andPredicateOperator = null;
    private static ?CompoundPredicateOperator $orPredicateOperator = null;

    public function __construct(CompoundPredicateLogicalType $operatorType, ComparisonPredicateModifier $modifier = ComparisonPredicateModifier::direct, #[ExpectedValues(flagsFromClass: ComparisonPredicateOptions::class)] int $options = ComparisonPredicateOptions::none)
    {
        parent::__construct(PredicateOperatorType::from($operatorType->value), $modifier, $options);
    }

    public static function notPredicateOperator(): CompoundPredicateOperator
    {
        if (!static::$notPredicateOperator) {
            static::$notPredicateOperator = new CompoundPredicateOperator(CompoundPredicateLogicalType::not);
        }
        return static::$notPredicateOperator;
    }

    public static function andPredicateOperator(): CompoundPredicateOperator
    {
        if (!static::$andPredicateOperator) {
            static::$andPredicateOperator = new CompoundPredicateOperator(CompoundPredicateLogicalType::and);
        }
        return static::$andPredicateOperator;
    }

    public static function orPredicateOperator(): CompoundPredicateOperator
    {
        if (!static::$orPredicateOperator) {
            static::$orPredicateOperator = new CompoundPredicateOperator(CompoundPredicateLogicalType::or);
        }
        return static::$orPredicateOperator;
    }

    public function compoundPredicateType(): CompoundPredicateLogicalType
    {
        return CompoundPredicateLogicalType::from($this->operatorType->value);
    }

    public function evaluatePredicates(ArrayClass $predicates, mixed $object = null, ?Dictionary $substitutionVariables = null): bool
    {
        if (!$predicates->isEmpty()) {
            $evaluations = $predicates->map(fn(Predicate $predicate): bool => $predicate->evaluate($object, $substitutionVariables));
            return match ($this->compoundPredicateType()) {
                CompoundPredicateLogicalType::not => !$evaluations->first(),
                CompoundPredicateLogicalType::and => !$evaluations->containsElement(false),
                CompoundPredicateLogicalType::or => $evaluations->containsElement(true),
            };
        }
        return true;
    }

    public function symbol(): string
    {
        return strtoupper($this->compoundPredicateType()->name);
    }
}
