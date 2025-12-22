<?php

namespace Sabatier\Foundation\Predicates;

use JetBrains\PhpStorm\ExpectedValues;
use Override;
use Sabatier\Foundation\Dictionary;
use function Sabatier\Foundation\human_readable_value;
use function Sabatier\Foundation\typeof;

/** @internal */
class VariableAssignmentExpression extends Expression
{
    public string $predicateFormat {
        get => sprintf("%s := %s", $this->assignmentVariable->predicateFormat, $this->subexpression->predicateFormat);
    }

    public function __construct(public readonly VariableExpression $assignmentVariable, public readonly Expression $subexpression)
    {
        parent::__construct(ExpressionType::variableAssignment);
        $this->variable = $this->assignmentVariable->variable;
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
        $this->assignmentVariable->accept($visitor, $flags);
        $this->subexpression->accept($visitor, $flags);
        if ($flags & PredicateVisitorFlags::internalNodes) {
            $visitor->visitPredicateExpression($this);
        }
    }

    #[Override]
    public function withSubstitutionVariables(Dictionary $variables): Expression
    {
        $assignmentVariable = $this->assignmentVariable->withSubstitutionVariables($variables);
        assert($assignmentVariable instanceof VariableExpression, sprintf("Invalid argument: expecting \"%s\", \"%s\" given", VariableExpression::class, typeof($assignmentVariable)));
        return new VariableAssignmentExpression($assignmentVariable, $this->subexpression->withSubstitutionVariables($variables));
    }

    #[Override]
    public function expressionValue(mixed $object = null, ?Dictionary $context = null): mixed
    {
        assert($context !== null, "Cannot evaluate variable assignment with nil bindings");
        $value = $this->subexpression->expressionValue($object, $context);
        $context[$this->variable] = $value;
        if (Predicate::$debugDefault) {
            error_log(sprintf("Foundation: %s %s: %s", $this->debugDescription, $this->expressionType->name, human_readable_value($value)));
        }
        return $value;
    }
}
