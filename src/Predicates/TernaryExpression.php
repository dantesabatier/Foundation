<?php

namespace Sabatier\Foundation\Predicates;

use JetBrains\PhpStorm\ExpectedValues;
use Override;
use Sabatier\Foundation\Dictionary;
use function Sabatier\Foundation\human_readable_value;

/** @internal */
final class TernaryExpression extends Expression
{
    #[Override]
    public string $predicateFormat {
        get => sprintf("TERNARY(%s, %s, %s)", $this->predicate, $this->true, $this->false);
    }

    public function __construct(Predicate $predicate, Expression $true, Expression $false)
    {
        parent::__construct(ExpressionType::conditional);
        $this->predicate = $predicate;
        $this->true = $true;
        $this->false = $false;
    }

    #[Override]
    public function withSubstitutionVariables(Dictionary $variables): Expression
    {
        return new TernaryExpression($this->predicate->withSubstitutionVariables($variables), $this->true->withSubstitutionVariables($variables), $this->false->withSubstitutionVariables($variables));
    }

    #[Override]
    public function expressionValue(mixed $object = null, ?Dictionary $context = null): mixed
    {
        $expression = $this->predicate->evaluate($object, $context) ? $this->true : $this->false;
        $value = $expression->expressionValue($object, $context);
        if (Predicate::$debugDefault) {
            $value
                |> human_readable_value(...)
                |> (fn(string $x): string => sprintf("Foundation: %s %s: %s", $this->debugDescription, $this->expressionType->name, $x))
                |> error_log(...);
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
        $this->predicate->accept($visitor, $flags);
        $this->true->accept($visitor, $flags);
        $this->false->accept($visitor, $flags);
        if ($flags & PredicateVisitorFlags::internalNodes) {
            $visitor->visitPredicateExpression($this);
        }
    }
}
