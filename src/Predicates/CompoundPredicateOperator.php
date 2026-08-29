<?php

/**
 * Created by PhpStorm.
 * User: dante
 * Date: 24/07/20
 * Time: 11:43
 */

declare(strict_types=1);

namespace Sabatier\Foundation\Predicates;

use JetBrains\PhpStorm\ExpectedValues;
use Override;
use Sabatier\Foundation\ArrayClass;
use Sabatier\Foundation\Dictionary;
use function Sabatier\Foundation\human_readable_value;

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
        return self::$notPredicateOperator ??= new CompoundPredicateOperator(CompoundPredicateLogicalType::not);
    }

    public static function andPredicateOperator(): CompoundPredicateOperator
    {
        return self::$andPredicateOperator ??= new CompoundPredicateOperator(CompoundPredicateLogicalType::and);
    }

    public static function orPredicateOperator(): CompoundPredicateOperator
    {
        return self::$orPredicateOperator ??= new CompoundPredicateOperator(CompoundPredicateLogicalType::or);
    }

    public function evaluatePredicates(ArrayClass $predicates, mixed $object = null, ?Dictionary $substitutionVariables = null): bool
    {
        $type = $this->compoundPredicateType;
        if ($predicates->isEmpty) {
            return $type === CompoundPredicateLogicalType::and;
        }
        if (Predicate::$debugDefault) {
            Predicate::debug(sprintf("%s %s (%d subpredicates)", $this->debugDescription, $type->name, $predicates->count));
        }
        // Evaluate incrementally so AND and OR do not execute branches after their result is known.
        $result = Predicate::debugNested(function () use ($type, $predicates, $object, $substitutionVariables): bool {
            if ($type === CompoundPredicateLogicalType::not) {
                return !$predicates->first->evaluate($object, $substitutionVariables);
            }
            foreach ($predicates as $predicate) {
                $value = $predicate->evaluate($object, $substitutionVariables);
                if ($type === CompoundPredicateLogicalType::and && !$value) {
                    return false;
                }
                if ($type === CompoundPredicateLogicalType::or && $value) {
                    return true;
                }
            }
            return $type === CompoundPredicateLogicalType::and;
        });
        if (Predicate::$debugDefault) {
            Predicate::debug(sprintf("%s %s => %s", $this->debugDescription, $type->name, human_readable_value($result)));
        }
        return $result;
    }
}
