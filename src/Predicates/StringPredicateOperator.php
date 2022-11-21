<?php

/**
 * Created by PhpStorm.
 * User: dante
 * Date: 24/07/20
 * Time: 11:51
 */

namespace Sabatier\Foundation\Predicates;

use InvalidArgumentException;
use JetBrains\PhpStorm\ExpectedValues;

/** @internal */
class StringPredicateOperator extends PredicateOperator
{
    public function __construct(PredicateOperatorType $operatorType, ComparisonPredicateModifier $modifier = ComparisonPredicateModifier::direct, #[ExpectedValues(flagsFromClass: ComparisonPredicateOptions::class)] int $options = ComparisonPredicateOptions::none)
    {
        $op = ComparisonPredicateOptions::none;
        if ($options & ComparisonPredicateOptions::caseInsensitive) {
            $op |= ComparisonPredicateOptions::caseInsensitive;
            if ($options & ComparisonPredicateOptions::diacriticInsensitive) {
                $op |= ComparisonPredicateOptions::diacriticInsensitive;
                if ($options & ComparisonPredicateOptions::normalized) {
                    $op |= ComparisonPredicateOptions::normalized;
                }
            }
        }
        if ($options & ComparisonPredicateOptions::localeSensitive) {
            throw new InvalidArgumentException(sprintf("%s comparison predicate option \"ComparisonPredicateOptions::localeSensitive\" is not supported by predicate operator %s", self::class, $operatorType->name));
        }
        parent::__construct($operatorType, $modifier, $op);
    }

    public function symbol(): string
    {
        $symbol = parent::symbol();
        $options = $this->options;
        if ($options) {
            $symbol .= '[';
            if ($options & ComparisonPredicateOptions::caseInsensitive) {
                $symbol .= 'c';
                if ($options & ComparisonPredicateOptions::diacriticInsensitive) {
                    $symbol .= 'd';
                    if ($options & ComparisonPredicateOptions::normalized) {
                        $symbol .= "n";
                    }
                }
            }
            if ($options & ComparisonPredicateOptions::localeSensitive) {
                $symbol .= "l";
            }
            $symbol .= ']';
        }
        return $symbol;
    }
}
