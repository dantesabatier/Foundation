<?php

namespace Sabatier\Foundation\Predicates;

use JetBrains\PhpStorm\ExpectedValues;
use Sabatier\Foundation\Dictionary;
use function Sabatier\Foundation\human_readable_value;

/** @internal */
class TernaryExpression extends Expression
{
    public function __construct(public readonly Predicate $predicate, public readonly Expression $true, public readonly Expression $false)
    {
        parent::__construct(ExpressionType::conditional);
    }

    public function withSubstitutionVariables(Dictionary $variables): Expression
    {
        return new TernaryExpression($this->predicate()->withSubstitutionVariables($variables), $this->true->withSubstitutionVariables($variables), $this->false->withSubstitutionVariables($variables));
    }

    public function expressionValue(mixed $object = null, ?Dictionary $context = null): mixed
    {
        $expression = $this->predicate->evaluate($object, $context) ? $this->true : $this->false;
        $value = $expression->expressionValue($object, $context);
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
        $this->predicate->accept($visitor, $flags);
        $this->true->accept($visitor, $flags);
        $this->false->accept($visitor, $flags);
        if ($flags & PredicateVisitorFlags::internalNodes) {
            $visitor->visitPredicateExpression($this);
        }
    }

    public function predicate(): Predicate
    {
        return $this->predicate;
    }

    public function true(): Expression
    {
        return $this->true;
    }

    public function false(): Expression
    {
        return $this->false;
    }

    public function predicateFormat(): string
    {
        return sprintf("TERNARY(%s, %s, %s)", $this->predicate, $this->true, $this->false);
    }
}
