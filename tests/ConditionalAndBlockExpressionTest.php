<?php

declare(strict_types=1);

namespace Sabatier\Foundation\Tests;

use Override;
use PHPUnit\Framework\TestCase;
use Sabatier\Foundation\ArrayClass;
use Sabatier\Foundation\Dictionary;
use Sabatier\Foundation\Predicates\BlockExpression;
use Sabatier\Foundation\Predicates\Expression;
use Sabatier\Foundation\Predicates\ExpressionType;
use Sabatier\Foundation\Predicates\Predicate;
use Sabatier\Foundation\Predicates\PredicateOperator;
use Sabatier\Foundation\Predicates\PredicateVisitor;
use Sabatier\Foundation\Predicates\PredicateVisitorFlags;
use Sabatier\Foundation\Predicates\TernaryExpression;

/**
 * Tests the two expression nodes that carry other nodes inside them rather than a value:
 * TernaryExpression, which picks between two subexpressions on a predicate, and
 * BlockExpression, which hands its evaluated arguments to a closure.
 *
 * Regression guards:
 *  - a conditional evaluates only the branch its predicate selects, and substitution
 *    rebuilds all three parts rather than sharing them with the original;
 *  - a block evaluates its arguments before calling the closure, so the closure receives
 *    values and never expressions;
 *  - substituting into a block rewrites its arguments while keeping the same closure,
 *    which is what lets a bound variable reach a block written before the binding
 *    existed;
 *  - accept() honours the visitor flags in both: the expressions flag gates the whole
 *    traversal, and internalNodes brackets the children with the parent node before and
 *    after. A block with no arguments still brackets, so a visitor sees the node itself.
 */
final class ConditionalAndBlockExpressionTest extends TestCase
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

    private function conditional(): Expression
    {
        return Expression::expressionForConditional(
            Predicate::format("n > 10"),
            Expression::expressionForConstantValue("high"),
            Expression::expressionForConstantValue("low"),
        );
    }

    public function testAConditionalDescribesItselfAsATernary(): void
    {
        $conditional = $this->conditional();

        $this->assertInstanceOf(TernaryExpression::class, $conditional);
        $this->assertSame(ExpressionType::conditional, $conditional->expressionType);
        $this->assertSame("TERNARY(n > 10, 'high', 'low')", $conditional->predicateFormat);
    }

    public function testAConditionalPicksTheBranchItsPredicateSelects(): void
    {
        $conditional = $this->conditional();

        $this->assertSame("high", $conditional->expressionValue(new Dictionary(["n" => 42])));
        $this->assertSame("low", $conditional->expressionValue(new Dictionary(["n" => 5])));
    }

    public function testSubstitutionRebuildsTheWholeConditional(): void
    {
        $conditional = $this->conditional();

        $substituted = $conditional->withSubstitutionVariables(new Dictionary());

        $this->assertInstanceOf(TernaryExpression::class, $substituted);
        $this->assertNotSame($conditional, $substituted, "all three parts are rebuilt rather than shared");
        $this->assertSame($conditional->predicateFormat, $substituted->predicateFormat);
    }

    public function testAConditionalBracketsItsChildrenForTheVisitor(): void
    {
        $events = [];

        $this->conditional()->accept($this->visitor($events), PredicateVisitorFlags::all);

        $this->assertSame("conditional", $events[0], "the node announces itself first");
        $this->assertSame("conditional", $events[count($events) - 1], "and again once its children are done");
        $this->assertContains("predicate", $events, "the predicate is traversed too");
    }

    public function testAConditionalIsNotTraversedWithoutTheExpressionsFlag(): void
    {
        $events = [];

        $this->conditional()->accept($this->visitor($events), PredicateVisitorFlags::operators);

        $this->assertSame([], $events);
    }

    public function testABlockDescribesItselfWithItsArguments(): void
    {
        $block = Expression::expressionForBlock(
            fn(mixed $object, ArrayClass $arguments, ?Dictionary $context): string => "unused",
            new ArrayClass([Expression::expressionForConstantValue(1)]),
        );

        $this->assertInstanceOf(BlockExpression::class, $block);
        $this->assertSame(ExpressionType::block, $block->expressionType);
        $this->assertSame("BLOCK(function, 1)", $block->predicateFormat);
    }

    public function testABlockWithoutArgumentsOmitsTheSeparator(): void
    {
        $block = Expression::expressionForBlock(fn(mixed $object, ArrayClass $arguments, ?Dictionary $context): string => "unused");

        $this->assertSame("BLOCK(function)", $block->predicateFormat);
    }

    public function testABlockReceivesItsArgumentsAlreadyEvaluated(): void
    {
        $received = null;
        $block = Expression::expressionForBlock(
            function (mixed $object, ArrayClass $arguments, ?Dictionary $context) use (&$received): string {
                $received = $arguments->array;
                return "done";
            },
            new ArrayClass([Expression::expressionForConstantValue(1), Expression::expressionForConstantValue("two")]),
        );

        $this->assertSame("done", $block->expressionValue(null));
        $this->assertSame([1, "two"], $received, "the closure is handed values, never expressions");
    }

    public function testABlockWithoutArgumentsIsCalledWithAnEmptyCollection(): void
    {
        $count = null;
        $block = Expression::expressionForBlock(function (mixed $object, ArrayClass $arguments, ?Dictionary $context) use (&$count): string {
            $count = $arguments->count;
            return "done";
        });

        $block->expressionValue(null);

        $this->assertSame(0, $count, "the closure never has to guard against a missing collection");
    }

    public function testABlockIsHandedTheObjectItIsEvaluatedAgainst(): void
    {
        $seen = null;
        $object = new Dictionary(["n" => 42]);
        $block = Expression::expressionForBlock(function (mixed $evaluated, ArrayClass $arguments, ?Dictionary $context) use (&$seen): string {
            $seen = $evaluated;
            return "done";
        });

        $block->expressionValue($object);

        $this->assertSame($object, $seen);
    }

    public function testSubstitutionRewritesTheArgumentsAndKeepsTheClosure(): void
    {
        $block = Expression::expressionForBlock(
            fn(mixed $object, ArrayClass $arguments, ?Dictionary $context): string => "kept",
            new ArrayClass([Expression::expressionForConstantValue(1), Expression::expressionForVariable("v")]),
        );

        $substituted = $block->withSubstitutionVariables(new Dictionary(["v" => Expression::expressionForConstantValue(9)]));

        $this->assertSame("BLOCK(function, 1, 9)", $substituted->predicateFormat, "the bound variable reaches the argument list");
        $this->assertSame("kept", $substituted->expressionValue(null), "and the closure survives the rewrite");
        $this->assertNotSame($block, $substituted);
    }

    public function testABlockBracketsItsArgumentsForTheVisitor(): void
    {
        $block = Expression::expressionForBlock(
            fn(mixed $object, ArrayClass $arguments, ?Dictionary $context): string => "unused",
            new ArrayClass([Expression::expressionForConstantValue(1), Expression::expressionForVariable("v")]),
        );
        $events = [];

        $block->accept($this->visitor($events), PredicateVisitorFlags::all);

        $this->assertSame(["block", "constantValue", "variable", "block"], $events);
    }

    public function testABlockWithoutArgumentsStillAnnouncesItself(): void
    {
        $block = Expression::expressionForBlock(fn(mixed $object, ArrayClass $arguments, ?Dictionary $context): string => "unused");
        $events = [];

        $block->accept($this->visitor($events), PredicateVisitorFlags::all);

        $this->assertSame(["block", "block"], $events, "an empty argument list still brackets");
    }

    public function testABlockIsNotTraversedWithoutTheExpressionsFlag(): void
    {
        $block = Expression::expressionForBlock(fn(mixed $object, ArrayClass $arguments, ?Dictionary $context): string => "unused");
        $events = [];

        $block->accept($this->visitor($events), PredicateVisitorFlags::operators);

        $this->assertSame([], $events);
    }
}
