<?php

declare(strict_types=1);

namespace Sabatier\Foundation\Tests;

use PHPUnit\Framework\TestCase;
use Sabatier\Foundation\Predicates\Expression;
use Sabatier\Foundation\Predicates\Predicate;
use Sabatier\Foundation\Predicates\PredicateOperator;
use Sabatier\Foundation\Predicates\PredicateVisitor;
use Sabatier\Foundation\Predicates\PredicateVisitorFlags;

final class PredicateVisitorTest extends TestCase
{
    public function testOperatorIsVisitedOnceBeforeExpressions(): void
    {
        $events = [];
        Predicate::format("x == 1")->accept($this->visitor($events), PredicateVisitorFlags::expressions | PredicateVisitorFlags::operators | PredicateVisitorFlags::operatorsBefore);

        $this->assertSame(["operator", "expression", "expression"], $events);
    }

    public function testOperatorIsVisitedOnceAfterExpressions(): void
    {
        $events = [];
        Predicate::format("x == 1")->accept($this->visitor($events), PredicateVisitorFlags::expressions | PredicateVisitorFlags::operators);

        $this->assertSame(["expression", "expression", "operator"], $events);
    }

    /**
     * @param list<string> $events
     */
    private function visitor(array &$events): PredicateVisitor
    {
        return new class ($events) implements PredicateVisitor {
            /** @param list<string> $events */
            public function __construct(private array &$events)
            {
            }

            public function visitPredicate(Predicate $predicate): void
            {
            }

            public function visitPredicateExpression(Expression $expression): void
            {
                $this->events[] = "expression";
            }

            public function visitPredicateOperator(PredicateOperator $operator): void
            {
                $this->events[] = "operator";
            }
        };
    }
}
