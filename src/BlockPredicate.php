<?php

/**
 * Created by PhpStorm.
 * User: dante
 * Date: 27/06/20
 * Time: 00:48
 */

namespace Sabatier\Foundation;

use Closure;
use JetBrains\PhpStorm\ExpectedValues;

/** @internal */
class BlockPredicate extends Predicate
{
    private Closure $block;

    public function __construct(Closure $block)
    {
        $this->block = $block;
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
