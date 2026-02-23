<?php

/**
 * Created by PhpStorm.
 * User: dante
 * Date: 24/07/20
 * Time: 11:43
 */

namespace Sabatier\Foundation\Predicates;

use JetBrains\PhpStorm\ExpectedValues;
use Override;
use Sabatier\Foundation\ArrayClass;
use Sabatier\Foundation\Dictionary;

/** @internal */
final class CompoundPredicateOperator extends PredicateOperator
{
    private static ?CompoundPredicateOperator $notPredicateOperator = null;
    private static ?CompoundPredicateOperator $andPredicateOperator = null;
    private static ?CompoundPredicateOperator $orPredicateOperator = null;
    public CompoundPredicateLogicalType $compoundPredicateType {
        get => CompoundPredicateLogicalType::from($this->operatorType->value);
    }
    #[Override]
    public string $symbol {
        get => strtoupper($this->compoundPredicateType->name);
    }

    public function __construct(CompoundPredicateLogicalType $operatorType, ComparisonPredicateModifier $modifier = ComparisonPredicateModifier::direct, #[ExpectedValues(flagsFromClass: ComparisonPredicateOptions::class)] int $options = ComparisonPredicateOptions::none)
    {
        parent::__construct(PredicateOperatorType::from($operatorType->value), $modifier, $options);
    }

    public static function notPredicateOperator(): CompoundPredicateOperator
    {
        self::$notPredicateOperator ??= new CompoundPredicateOperator(CompoundPredicateLogicalType::not);
        return self::$notPredicateOperator;
    }

    public static function andPredicateOperator(): CompoundPredicateOperator
    {
        self::$andPredicateOperator ??= new CompoundPredicateOperator(CompoundPredicateLogicalType::and);
        return self::$andPredicateOperator;
    }

    public static function orPredicateOperator(): CompoundPredicateOperator
    {
        self::$orPredicateOperator ??= new CompoundPredicateOperator(CompoundPredicateLogicalType::or);
        return self::$orPredicateOperator;
    }

    public function evaluatePredicates(ArrayClass $predicates, mixed $object = null, ?Dictionary $substitutionVariables = null): bool
    {
        if (!$predicates->isEmpty) {
            $evaluations = $predicates->map(fn(Predicate $predicate): bool => $predicate->evaluate($object, $substitutionVariables));
            return match ($this->compoundPredicateType) {
                CompoundPredicateLogicalType::not => !$evaluations->first,
                CompoundPredicateLogicalType::and => !$evaluations->containsElement(false),
                CompoundPredicateLogicalType::or => $evaluations->containsElement(true),
            };
        }
        return true;
    }
}
