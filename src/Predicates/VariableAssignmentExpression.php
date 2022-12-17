<?php

namespace Sabatier\Foundation\Predicates;

use JetBrains\PhpStorm\ExpectedValues;
use Sabatier\Foundation\Dictionary;
use function Sabatier\Foundation\human_readable_value;
use function Sabatier\Foundation\typeof;

/** @internal */
class VariableAssignmentExpression extends Expression
{
    public function __construct(private readonly VariableExpression $assignmentVariable, private readonly Expression $subexpression)
    {
        parent::__construct(ExpressionType::variableAssignment);
    }

    public function assignmentVariable(): VariableExpression
    {
        return $this->assignmentVariable;
    }

    public function subexpression(): Expression
    {
        return $this->subexpression;
    }

    public function variable(): string
    {
        return $this->assignmentVariable->variable();
    }

    public function predicateFormat(): string
    {
        return sprintf("%s := %s", $this->assignmentVariable->predicateFormat(), $this->subexpression->predicateFormat());
    }

    public function accept(PredicateVisitor $visitor, #[ExpectedValues(flagsFromClass: PredicateVisitorFlags::class)] int $flags): void
    {
        if (!($flags & PredicateVisitorFlags::expressions)) {
            return;
        }
        if ($flags & PredicateVisitorFlags::internalNodes) {
            $visitor->visitPredicateExpression($this);
        }
        $this->assignmentVariable()->accept($visitor, $flags);
        $this->subexpression()->accept($visitor, $flags);
        if ($flags & PredicateVisitorFlags::internalNodes) {
            $visitor->visitPredicateExpression($this);
        }
    }

    public function withSubstitutionVariables(Dictionary $variables): Expression
    {
        $assignmentVariable = $this->assignmentVariable()->withSubstitutionVariables($variables);
        assert($assignmentVariable instanceof VariableExpression, sprintf("Invalid argument: expecting \"%s\", \"%s\" given", VariableExpression::class, typeof($assignmentVariable)));
        return new VariableAssignmentExpression($assignmentVariable, $this->subexpression()->withSubstitutionVariables($variables));
    }

    public function expressionValue(mixed $object = null, ?Dictionary $context = null): mixed
    {
        assert($context !== null, "Cannot evaluate variable assignment with nil bindings");
        $value = $this->subexpression()->expressionValue($object, $context);
        $context[$this->variable()] = $value;
        if (Predicate::$debugDefault) {
            error_log(sprintf("Foundation: expression %s: %s", $this->expressionType->name, human_readable_value($value)));
        }
        return $value;
    }
}
