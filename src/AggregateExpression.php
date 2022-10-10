<?php

/**
 * @author Dante Sabatier <dantesabatier@me.com>
 * @version 1.0
 * @package Sabatier\Foundation
 */

namespace Sabatier\Foundation;

use JetBrains\PhpStorm\ExpectedValues;
use JetBrains\PhpStorm\Pure;

/** @internal */
class AggregateExpression extends Expression
{
    #[Pure]
    public function __construct(private readonly ArrayClass $collection)
    {
        parent::__construct(ExpressionType::aggregate);
    }

    public function withSubstitutionVariables(Dictionary $variables): Expression
    {
        return new self($this->collection->map(fn(Expression $expression): Expression => $expression->withSubstitutionVariables($variables)));
    }

    public function expressionValue(mixed $object = null, ?Dictionary $context = null): mixed
    {
        $value = $this->collection->compactMap(fn(Expression $expression): mixed => $expression->expressionValue($object, $context));
        if (Predicate::$debugDefault) {
            error_log(sprintf("Foundation: expression %s: %s", $this->expressionType->name, human_readable_value($value)));
        }
        return $value;
    }

    public function accept(PredicateVisitor $visitor, #[ExpectedValues(flagsFromClass: PredicateVisitorFlags::class)] int $flags): void
    {
        if (!($flags & PredicateVisitorFlags::expressions)) {
            return;
        }
        if ($flags & PredicateVisitorFlags::internalNodes) {
            $visitor->visitPredicateExpression($this);
        }
        foreach ($this->collection() as $expression) {
            $expression->accept($visitor, $flags);
        }
        if ($flags & PredicateVisitorFlags::internalNodes) {
            $visitor->visitPredicateExpression($this);
        }
    }

    public function collection(): ArrayClass
    {
        return $this->collection;
    }

    public function predicateFormat(): string
    {
        return "{" . $this->collection()->join(', ') . "}";
    }
}
