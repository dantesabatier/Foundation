<?php

/**
 * Created by PhpStorm.
 * User: dante
 * Date: 24/07/20
 * Time: 09:37
 */

namespace Sabatier\Foundation\Predicates;

interface PredicateVisitor
{
    public function visitPredicate(Predicate $predicate): void;

    public function visitPredicateExpression(Expression $expression): void;

    public function visitPredicateOperator(PredicateOperator $operator): void;
}
