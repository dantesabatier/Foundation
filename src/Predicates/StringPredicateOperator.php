<?php

/**
 * Created by PhpStorm.
 * User: dante
 * Date: 24/07/20
 * Time: 11:51
 */

declare(strict_types=1);

namespace Sabatier\Foundation\Predicates;

use JetBrains\PhpStorm\ExpectedValues;
use Override;
use function Sabatier\Foundation\fatal_error;

/** @internal */
class StringPredicateOperator extends PredicateOperator
{
    #[Override]
    public string $symbol {
        get {
            $symbol = parent::$symbol::get();
            $options = $this->options;
            if ($options) {
                $symbol .= "[";
                if ($options & ComparisonPredicateOptions::caseInsensitive) {
                    $symbol .= "c";
                    if ($options & ComparisonPredicateOptions::diacriticInsensitive) {
                        $symbol .= "d";
                        if ($options & ComparisonPredicateOptions::normalized) {
                            $symbol .= "n";
                        }
                    }
                }
                if ($options & ComparisonPredicateOptions::localeSensitive) {
                    $symbol .= "l";
                }
                $symbol .= "]";
            }
            return $symbol;
        }
    }

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
            fatal_error(sprintf("%s comparison predicate option \"ComparisonPredicateOptions::localeSensitive\" is not supported by predicate operator %s", $this->class, $operatorType->name));
        }
        parent::__construct($operatorType, $modifier, $op);
    }
}
