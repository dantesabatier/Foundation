<?php

/**
 * Created by PhpStorm.
 * User: dante
 * Date: 27/06/20
 * Time: 00:48
 */

namespace Sabatier\Foundation\Predicates;

use Closure;
use JetBrains\PhpStorm\ExpectedValues;
use Sabatier\Foundation\Dictionary;

/** @internal */
class BlockPredicate extends Predicate
{
    public function __construct(private readonly Closure $block)
    {
    }

    public function withSubstitutionVariables(Dictionary $variables): Predicate
    {
        return $this;
    }

    public function evaluate(mixed $object = null, ?Dictionary $substitutionVariables = null): bool
    {
        return (bool)($this->block)($object, $substitutionVariables);
    }

    public function accept(PredicateVisitor $visitor, #[ExpectedValues(flagsFromClass: PredicateVisitorFlags::class)] int $flags): void
    {
        $visitor->visitPredicate($this);
    }

    public function predicateFormat(): string
    {
        return "BLOCKPREDICATE()";
    }
}
