<?php

namespace Sabatier\Foundation\Predicates;

use JetBrains\PhpStorm\ExpectedValues;
use Override;
use Sabatier\Foundation\ArrayClass;
use Sabatier\Foundation\Collection;
use Sabatier\Foundation\Dictionary;
use function Sabatier\Foundation\human_readable_value;

/** @internal */
class SubqueryExpression extends Expression
{
    public function __construct(private readonly Expression $collectionExpression, private readonly Expression $variableExpression, private readonly Predicate $predicate)
    {
        parent::__construct(ExpressionType::subquery);
    }

    #[Override]
    public function expressionValue(mixed $object = null, ?Dictionary $context = null): Collection
    {
        /** @var ArrayClass $collection */
        $collection = $this->collectionExpression->expressionValue($object, $context) ?? new ArrayClass();
        assert($collection instanceof Collection);
        /** @var Dictionary|null $context */
        $context ??= new Dictionary();
        $context[$this->variable()] ??= Expression::expressionForEvaluatedObject();
        $predicate = $this->predicate->withSubstitutionVariables($context);
        $value = $collection->filter(fn(mixed $obj): bool => $predicate->evaluate($obj, $context));
        if (Predicate::$debugDefault) {
            error_log(sprintf("Foundation: expression %s: %s %s => %s", $this->expressionType->name, $collection->join(", "), $predicate->predicateFormat(), human_readable_value($value)));
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

    public function collectionExpression(): Expression
    {
        return $this->collectionExpression;
    }

    public function variableExpression(): Expression
    {
        return $this->variableExpression;
    }

    #[Override]
    public function variable(): string
    {
        return $this->variableExpression->variable();
    }

    #[Override]
    public function predicate(): Predicate
    {
        return $this->predicate;
    }

    #[Override]
    public function predicateFormat(): string
    {
        return sprintf("SUBQUERY(%s, %s, %s)", $this->collectionExpression()->description(), $this->variableExpression()->description(), $this->predicate()->description());
    }
}
