<?php

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
    public string $predicateFormat {
        get => sprintf("SUBQUERY(%s, %s, %s)", $this->collectionExpression->description, $this->variableExpression->description, $this->predicate->description);
    }

    public function __construct(public Expression $collectionExpression, public readonly Expression $variableExpression, public Predicate $predicate)
    {
        parent::__construct(ExpressionType::subquery);
        $this->variable = $this->variableExpression->variable;
        $this->collection = $this->collectionExpression->expressionValue() ?? new ArrayClass();
    }

    #[Override]
    public function expressionValue(mixed $object = null, ?Dictionary $context = null): Collection
    {
        /** @var ArrayClass $collection */
        $collection = $this->collectionExpression->expressionValue($object, $context) ?? new ArrayClass();
        assert($collection instanceof Collection);
        $context ??= new Dictionary();
        /** @psalm-suppress InvalidArgument */
        $context[$this->variable] ??= Expression::expressionForEvaluatedObject();
        $predicate = $this->predicate->withSubstitutionVariables($context);
        $value = $collection->filter(fn(mixed $obj): bool => $predicate->evaluate($obj, $context));
        if (Predicate::$debugDefault) {
            error_log(sprintf("Foundation: %s %s: %s %s => %s", $this->debugDescription, $this->expressionType->name, $collection->join(", "), $predicate->predicateFormat, human_readable_value($value)));
        }
        $this->collection = $collection;
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
