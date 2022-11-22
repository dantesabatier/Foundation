<?php

/**
 * Created by PhpStorm.
 * User: dante
 * Date: 24/07/20
 * Time: 09:40
 */

namespace Sabatier\Foundation\Predicates;

use InvalidArgumentException;
use JetBrains\PhpStorm\ExpectedValues;
use Sabatier\Foundation\CompareOptions;
use Sabatier\Foundation\ExpressibleByArrayLiteral;
use Sabatier\Foundation\ObjectClass;
use function Sabatier\Foundation\request_concrete_implementation;
use function Sabatier\Foundation\typeof;

/** @internal */
class PredicateOperator extends ObjectClass
{
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
            default => throw new InvalidArgumentException(sprintf("invalid argument: %s", $type->name)),
        };
    }

    public function performOperation(mixed $left, mixed $right): bool
    {
        if ($this->modifier === ComparisonPredicateModifier::direct) {
            return $this->performPrimitiveOperation($left, $right);
        }
        if ($left === null) {
            return match ($this->modifier) {
                ComparisonPredicateModifier::all => true,
                ComparisonPredicateModifier::any => false,
                default => throw new InvalidArgumentException(sprintf("invalid argument: %s", $this->modifier->name)),
            };
        }
        if ($left instanceof ExpressibleByArrayLiteral) {
            $left = $left->toArray();
        }
        if (!is_array($left)) {
            throw new InvalidArgumentException(sprintf("Invalid argument: the left hand side for an ALL or ANY modifier must be an Array or a Set, \"%s\" given", typeof($left)));
        }
        if (empty($left)) {
            return false;
        }
        switch ($this->modifier) {
            case ComparisonPredicateModifier::all:
                foreach ($left as $obj) {
                    if (!$this->performPrimitiveOperation($obj, $right)) {
                        return false;
                    }
                }
                return true;
            case ComparisonPredicateModifier::any:
                foreach ($left as $obj) {
                    if ($this->performPrimitiveOperation($obj, $right)) {
                        return true;
                    }
                }
                return false;
            default:
                throw new InvalidArgumentException(sprintf("Bad predicate operator modifier: %s", $this->modifier->name));
        }
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
    
    #[ExpectedValues(flagsFromClass: CompareOptions::class)]
    public function compareOptions(): int
    {
        return $this->options;
    }

    public function symbol(): string
    {
        return match ($this->operatorType) {
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
            default => throw new InvalidArgumentException()
        };
    }

    public function predicateFormat(): string
    {
        return $this->symbol();
    }

    public function description(): string
    {
        return $this->predicateFormat();
    }
}
