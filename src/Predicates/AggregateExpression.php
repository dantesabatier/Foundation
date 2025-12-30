<?php

namespace Sabatier\Foundation\Predicates;

use JetBrains\PhpStorm\ExpectedValues;
use Override;
use Sabatier\Foundation\ArrayClass;
use Sabatier\Foundation\Dictionary;
use function Sabatier\Foundation\human_readable_value;

/** @internal */
final class AggregateExpression extends Expression
{
    public string $predicateFormat {
        get => "{{$this->collection->join(", ")}}";
    }

    /**
     * @param ArrayClass<Expression> $collection
     */
    public function __construct(ArrayClass $collection)
    {
        parent::__construct(ExpressionType::aggregate);
        $this->collection = $collection;
    }

    #[Override]
    public function withSubstitutionVariables(Dictionary $variables): Expression
    {
        return new self($this->collection->map(fn(Expression $expression): Expression => $expression->withSubstitutionVariables($variables)));
    }

    #[Override]
    public function expressionValue(mixed $object = null, ?Dictionary $context = null): ArrayClass
    {
        $value = $this->collection->compactMap(fn(Expression $expression): mixed => $expression->expressionValue($object, $context));
        if (Predicate::$debugDefault) {
            error_log(sprintf("Foundation: %s %s: %s", $this->debugDescription, $this->expressionType->name, human_readable_value($value)));
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
        foreach ($this->collection as $expression) {
            $expression->accept($visitor, $flags);
        }
        if ($flags & PredicateVisitorFlags::internalNodes) {
            $visitor->visitPredicateExpression($this);
        }
    }
}
