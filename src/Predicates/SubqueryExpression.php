<?php

declare(strict_types=1);

namespace Sabatier\Foundation\Predicates;

use JetBrains\PhpStorm\ExpectedValues;
use Override;
use Sabatier\Foundation\ArrayClass;
use Sabatier\Foundation\Collection;
use Sabatier\Foundation\Dictionary;
use function Sabatier\Foundation\human_readable_value;

/** @internal */
final class SubqueryExpression extends Expression
{
    public readonly Expression $collectionExpression;
    public readonly Expression $variableExpression;
    #[Override]
    public string $predicateFormat {
        get => sprintf("SUBQUERY(%s, %s, %s)", $this->collectionExpression->description, $this->variableExpression->description, $this->predicate->description);
    }

    public function __construct(Expression $collectionExpression, Expression $variableExpression, Predicate $predicate)
    {
        parent::__construct(ExpressionType::subquery);
        $this->predicate = $predicate;
        $this->variableExpression = $variableExpression;
        $this->collectionExpression = $collectionExpression;
        $this->variable = $this->variableExpression->variable;
        $this->collection = $this->collectionExpression->expressionValue() ?? new ArrayClass();
    }

    #[Override]
    public function expressionValue(mixed $object = null, ?Dictionary $context = null): Collection
    {
        /** @var ArrayClass $collection */
        $collection = $this->collectionExpression->expressionValue($object, $context) ?? new ArrayClass();
        assert($collection instanceof Collection);
        $this->collection = $collection;
        /** @var Dictionary<mixed> $bindings */
        $bindings = $context ?? new Dictionary();
        $hadVariable = $bindings->offsetExists($this->variable);
        $previousValue = $bindings[$this->variable];
        $bindings[$this->variable] = Expression::expressionForEvaluatedObject();
        try {
            $predicate = $this->predicate->withSubstitutionVariables($bindings);
            $value = $collection->filter(fn(mixed $obj): bool => $predicate->evaluate($obj, $bindings));
        } finally {
            if ($hadVariable) {
                $bindings[$this->variable] = $previousValue;
            } else {
                $bindings->offsetUnset($this->variable);
            }
        }
        if (Predicate::$debugDefault) {
            Predicate::debug(sprintf("%s %s: %s %s => %s", $this->debugDescription, $this->expressionType->name, $collection->join(", "), $predicate->predicateFormat, human_readable_value($value)));
        }
        return $value;
    }

    #[Override]
    public function accept(PredicateVisitor $visitor, #[ExpectedValues(flagsFromClass: PredicateVisitorFlags::class)] int $flags): void
    {
        if (!($flags & PredicateVisitorFlags::expressions)) {
            return;
        }
        if ($flags & PredicateVisitorFlags::internalNodes) {
            $visitor->visitPredicateExpression($this);
        }
        $this->collectionExpression->accept($visitor, $flags);
        $this->variableExpression->accept($visitor, $flags);
        $this->predicate->accept($visitor, $flags);
        if ($flags & PredicateVisitorFlags::internalNodes) {
            $visitor->visitPredicateExpression($this);
        }
    }
}
