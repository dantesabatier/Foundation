<?php

declare(strict_types=1);

/**
 * Created by PhpStorm.
 * User: dante
 * Date: 24/07/20
 * Time: 11:44
 */
namespace Sabatier\Foundation\Predicates;

use JetBrains\PhpStorm\ExpectedValues;
use Override;
use Sabatier\Foundation\CompareOptions;
use function Sabatier\Foundation\string_is_equal;

/** @internal */
final class EqualityPredicateOperator extends PredicateOperator
{
    #[Override]
    public string $symbol {
        // performPrimitiveOperation() compares through string_is_equal() with these options, so the symbol has to carry them: without this "s ==[cd] \"JOSE\"" read back as "s = 'JOSE'" and looked case-sensitive while still matching "josé".
        get => $this->symbolWithOptions(parent::$symbol::get());
    }

    public function __construct(PredicateOperatorType $operatorType, ComparisonPredicateModifier $modifier = ComparisonPredicateModifier::direct, #[ExpectedValues(flagsFromClass: ComparisonPredicateOptions::class)] int $options = ComparisonPredicateOptions::none, public readonly bool $isNegation = false)
    {
        parent::__construct($operatorType, $modifier, $options);
    }

    #[Override]
    protected function performPrimitiveOperation(mixed $left, mixed $right): bool
    {
        if ($left === "") {
            $left = null;
        }
        if ($right === "") {
            $right = null;
        }
        $isNegation = $this->isNegation;
        if ($left === null && $right === null) {
            return !$isNegation;
        }
        if ($left === null || $right === null) {
            return $isNegation;
        }
        $this->coerce($left, $right);
        $options = $this->compareOptions;
        if ($options === CompareOptions::none || !is_string($left) || !is_string($right)) {
            return $isNegation xor ($left === $right);
        }
        if (string_is_equal($left, $right, $options)) {
            return !$isNegation;
        }
        return $isNegation;
    }
}
