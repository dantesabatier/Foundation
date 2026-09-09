<?php

declare(strict_types=1);

namespace Sabatier\Foundation\Tests;

use Override;
use PHPUnit\Framework\TestCase;
use Sabatier\Foundation\Dictionary;
use Sabatier\Foundation\InternalInconsistencyException;
use Sabatier\Foundation\ObjectClass;
use Sabatier\Foundation\Predicates\AnyKeyExpression;
use Sabatier\Foundation\Predicates\CustomPredicateOperator;
use Sabatier\Foundation\Predicates\Expression;
use Sabatier\Foundation\Predicates\ExpressionType;
use Sabatier\Foundation\Predicates\Predicate;
use Sabatier\Foundation\Predicates\PredicateOperator;
use Sabatier\Foundation\Predicates\PredicateOperatorType;
use Sabatier\Foundation\Predicates\PredicateVisitor;
use Sabatier\Foundation\Predicates\PredicateVisitorFlags;
use Sabatier\Foundation\Predicates\SymbolicExpression;
use Sabatier\Foundation\Predicates\VariableAssignmentExpression;
use Sabatier\Foundation\Predicates\VariableExpression;

/**
 * Tests for the expression and operator nodes that no other predicate suite reaches:
 * VariableAssignmentExpression, SymbolicExpression, AnyKeyExpression and CustomPredicateOperator.
 *
 * Regression guards:
 *  - a variable assignment evaluates its subexpression and binds the result into the
 *    context, so a later reference to the same variable resolves to it;
 *  - substitution rebuilds the assignment and rejects a binding that is not itself a
 *    VariableExpression, rather than silently producing a malformed node;
 *  - accept() honours the visitor flags: expressions gates the whole traversal, and
 *    internalNodes brackets the children with the parent node before and after;
 *  - a symbolic expression is inert — it evaluates to itself and compares by token;
 *  - AnyKeyExpression is a shared singleton that refuses to be evaluated;
 *  - a custom operator dispatches the selector on the left operand and rejects a
 *    non-object left-hand side.
 */
final class PredicateExpressionNodeTest extends TestCase
{
    /** @param list<string> $events */
    private function visitor(array &$events): PredicateVisitor
    {
        return new class ($events) implements PredicateVisitor {
            /** @param list<string> $events */
            public function __construct(private array &$events)
            {
            }

            #[Override]
            public function visitPredicate(Predicate $predicate): void
            {
                $this->events[] = "predicate";
            }

            #[Override]
            public function visitPredicateExpression(Expression $expression): void
            {
                $this->events[] = $expression->expressionType->name;
            }

            #[Override]
            public function visitPredicateOperator(PredicateOperator $operator): void
            {
                $this->events[] = "operator";
            }
        };
    }

    public function testAssignmentDescribesItselfAsTheFormatItParsesFrom(): void
    {
        $assignment = Expression::expressionForVariableNameAssignment("x", Expression::expressionForConstantValue(42));

        $this->assertInstanceOf(VariableAssignmentExpression::class, $assignment);
        $this->assertSame("x := 42", $assignment->predicateFormat);
        $this->assertSame(ExpressionType::variableAssignment, $assignment->expressionType);
        $this->assertSame("x", $assignment->variable, "the node adopts the variable it assigns to");
    }

    public function testAssignmentBindsItsValueIntoTheContext(): void
    {
        $assignment = Expression::expressionForVariableNameAssignment("x", Expression::expressionForConstantValue(42));
        $context = new Dictionary();

        $this->assertSame(42, $assignment->expressionValue(null, $context), "the assigned value is also the expression's value");
        $this->assertSame(42, $context["x"], "the binding outlives the evaluation");
    }

    public function testAValueBoundByAnAssignmentResolvesForALaterReference(): void
    {
        $context = new Dictionary();
        Expression::expressionForVariableNameAssignment("x", Expression::expressionForConstantValue(7))->expressionValue(null, $context);

        $this->assertSame(7, new VariableExpression("x")->expressionValue(null, $context));
    }

    public function testSubstitutionRebuildsTheAssignmentAroundTheNewVariable(): void
    {
        $assignment = Expression::expressionForVariableNameAssignment("x", Expression::expressionForConstantValue(42));

        $substituted = $assignment->withSubstitutionVariables(new Dictionary(["x" => new VariableExpression("y")]));

        $this->assertInstanceOf(VariableAssignmentExpression::class, $substituted);
        $this->assertSame("y := 42", $substituted->predicateFormat);
        $this->assertNotSame($assignment, $substituted, "substitution returns a new node");
    }

    public function testSubstitutingANonVariableIntoTheAssignmentTargetIsFatal(): void
    {
        $assignment = Expression::expressionForVariableNameAssignment("x", Expression::expressionForConstantValue(42));

        $this->expectException(InternalInconsistencyException::class);
        $this->expectExceptionMessage(VariableExpression::class);

        $assignment->withSubstitutionVariables(new Dictionary(["x" => 5]));
    }

    public function testInternalNodesBracketTheAssignmentAroundItsChildren(): void
    {
        $assignment = Expression::expressionForVariableNameAssignment("v", Expression::expressionForConstantValue(1));
        $events = [];

        $assignment->accept($this->visitor($events), PredicateVisitorFlags::all);

        $this->assertSame(["variableAssignment", "variable", "constantValue", "variableAssignment"], $events);
    }

    public function testWithoutInternalNodesOnlyTheChildrenAreVisited(): void
    {
        $assignment = Expression::expressionForVariableNameAssignment("v", Expression::expressionForConstantValue(1));
        $events = [];

        $assignment->accept($this->visitor($events), PredicateVisitorFlags::expressions);

        $this->assertSame(["variable", "constantValue"], $events);
    }

    public function testWithoutTheExpressionsFlagTheAssignmentIsNotTraversed(): void
    {
        $assignment = Expression::expressionForVariableNameAssignment("v", Expression::expressionForConstantValue(1));
        $events = [];

        $assignment->accept($this->visitor($events), PredicateVisitorFlags::operators);

        $this->assertSame([], $events);
    }

    public function testASymbolicExpressionIsInert(): void
    {
        $symbolic = Expression::expressionForSymbolicString("ANY");

        $this->assertInstanceOf(SymbolicExpression::class, $symbolic);
        $this->assertSame("ANY", $symbolic->predicateFormat);
        $this->assertSame(ExpressionType::symbolic, $symbolic->expressionType);
        $this->assertSame($symbolic, $symbolic->expressionValue(), "it evaluates to itself rather than a value");
    }

    public function testSymbolicExpressionsCompareByToken(): void
    {
        $symbolic = Expression::expressionForSymbolicString("ANY");

        $this->assertTrue($symbolic->isEqual(Expression::expressionForSymbolicString("ANY")), "the same token is equal across instances");
        $this->assertFalse($symbolic->isEqual(Expression::expressionForSymbolicString("ALL")));
        $this->assertFalse($symbolic->isEqual("ANY"), "a bare string is not a symbolic expression");
    }

    public function testAnyKeyIsASharedSingleton(): void
    {
        $anyKey = AnyKeyExpression::default();

        $this->assertSame($anyKey, AnyKeyExpression::default());
        $this->assertSame("ANYKEY", $anyKey->predicateFormat);
        $this->assertSame(ExpressionType::anyKey, $anyKey->expressionType);
    }

    public function testAnyKeyRefusesToBeEvaluated(): void
    {
        $this->expectException(InternalInconsistencyException::class);
        $this->expectExceptionMessage("Cannot evaluate any key expression");

        AnyKeyExpression::default()->expressionValue();
    }

    public function testACustomOperatorDispatchesItsSelectorOnTheLeftOperand(): void
    {
        $operator = new CustomPredicateOperator("matchesToken");

        $this->assertSame("matchesToken", $operator->symbol, "the selector is the operator's symbol");
        $this->assertSame(PredicateOperatorType::customSelector, $operator->operatorType);
        $this->assertTrue($operator->performOperation(new PredicateExpressionNodeTestBox(), "yes"));
        $this->assertFalse($operator->performOperation(new PredicateExpressionNodeTestBox(), "no"));
    }

    public function testACustomOperatorRejectsANonObjectLeftOperand(): void
    {
        $operator = new CustomPredicateOperator("matchesToken");

        $this->expectException(InternalInconsistencyException::class);
        $this->expectExceptionMessage("expecting \"object\"");

        $operator->performOperation("not an object", "yes");
    }
}

final class PredicateExpressionNodeTestBox extends ObjectClass
{
    /**
     * Answers the custom operator's selector.
     * @param mixed $other The right-hand operand the predicate supplies.
     * @return bool true when `$other` is the token this box recognises.
     */
    public function matchesToken(mixed $other): bool
    {
        return $other === "yes";
    }
}
