<?php

/**
 * @author Dante Sabatier <dantesabatier@me.com>
 * @version 1.0
 * @package Sabatier\Foundation
 */

namespace Sabatier\Foundation;

use JetBrains\PhpStorm\ExpectedValues;

/**
 * Class ComparisonPredicate
 * A specialized predicate that you use to compare expressions.
 * You use comparison predicates to compare the results of two expressions.
 * You create a comparison predicate with an operator, a left expression, and a right expression.
 * You represent the expressions using instances of the {@see Expression} class. When you evaluate the predicate, it returns as a BOOL value the result of invoking the operator with the results of evaluating the expressions.
 * @package Sabatier\Foundation
 */
class ComparisonPredicate extends Predicate
{
    private readonly PredicateOperator $predicateOperator;
    /** @var ComparisonPredicateModifier The comparison predicate modifier for the receiver. The default value is {@see ComparisonPredicateModifier::direct}. */
    public readonly ComparisonPredicateModifier $comparisonPredicateModifier;
    /** @var int The options that are set for the receiver. */
    #[ExpectedValues(flagsFromClass: ComparisonPredicateOptions::class)]
    public readonly int $options;
    /** @var PredicateOperatorType The predicate type for the receiver. */
    public readonly PredicateOperatorType $predicateOperatorType;

    /**
     * Initializes a predicate formed by combining given left and right expressions using a given selector.
     * @param Expression $leftExpression The left hand expression.
     * @param Expression $rightExpression The right hand expression.
     * @param PredicateOperatorType $type The predicate operator type (see {@see PredicateOperatorType}).
     * @param ComparisonPredicateModifier $modifier The modifier to apply (see {@see ComparisonPredicateModifier}).
     * @param int $options The options to apply (see {@see ComparisonPredicateOptions}). For no options, pass 0.
     * @param string|null $selector The selector to use. The method defined by the selector must take a single argument and return a BOOL value.
     */
    public function __construct(public readonly Expression $leftExpression, public readonly Expression $rightExpression, PredicateOperatorType $type = PredicateOperatorType::equalTo, ComparisonPredicateModifier $modifier = ComparisonPredicateModifier::direct, #[ExpectedValues(flagsFromClass: ComparisonPredicateOptions::class)] int $options = ComparisonPredicateOptions::none, ?string $selector = null)
    {
        $this->predicateOperator = $selector ? new CustomPredicateOperator($selector) : PredicateOperator::newOperator($type, $modifier, $options);
        $this->comparisonPredicateModifier = $this->predicateOperator->modifier;
        $this->options = $this->predicateOperator->options;
        $this->predicateOperatorType = $this->predicateOperator->operatorType;
    }

    public function predicateFormat(): string
    {
        $modifierDescription = '';
        $modifier = $this->comparisonPredicateModifier;
        switch ($modifier) {
            case ComparisonPredicateModifier::all:
            case ComparisonPredicateModifier::any:
                $modifierDescription = strtoupper($modifier->name) . ' ';
                break;
            default:
                break;
        }
        return sprintf('%s%s %s %s', $modifierDescription, $this->leftExpression->predicateFormat(), $this->predicateOperator->predicateFormat(), $this->rightExpression->predicateFormat());
    }

    public function withSubstitutionVariables(Dictionary $variables): Predicate
    {
        return new ComparisonPredicate($this->leftExpression->withSubstitutionVariables($variables), $this->rightExpression->withSubstitutionVariables($variables), $this->predicateOperatorType, $this->comparisonPredicateModifier, $this->options);
    }

    public function evaluate(mixed $object = null, ?Dictionary $substitutionVariables = null): bool
    {
        return $this->predicateOperator->performOperation($this->leftExpression->expressionValue($object, $substitutionVariables), $this->rightExpression->expressionValue($object, $substitutionVariables));
    }

    public function accept(PredicateVisitor $visitor, #[ExpectedValues(flagsFromClass: PredicateVisitorFlags::class)] int $flags): void
    {
        if (!($flags & PredicateVisitorFlags::expressions)) {
            return;
        }
        if ($flags & PredicateVisitorFlags::internalNodes) {
            $visitor->visitPredicate($this);
        }
        if (($flags & PredicateVisitorFlags::operators) && ($flags & PredicateVisitorFlags::operatorsBefore)) {
            $this->predicateOperator->accept($visitor, $flags);
        }
        if ($flags & PredicateVisitorFlags::expressions) {
            $this->leftExpression->accept($visitor, $flags);
            $this->rightExpression->accept($visitor, $flags);
        }
        if ($flags & PredicateVisitorFlags::operators) {
            $this->predicateOperator->accept($visitor, $flags);
        }
        if ($flags & PredicateVisitorFlags::internalNodes) {
            $visitor->visitPredicate($this);
        }
    }
}
