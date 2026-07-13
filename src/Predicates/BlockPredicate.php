<?php

/**
 * Created by PhpStorm.
 * User: dante
 * Date: 27/06/20
 * Time: 00:48
 */

declare(strict_types=1);

namespace Sabatier\Foundation\Predicates;

use Closure;
use JetBrains\PhpStorm\ExpectedValues;
use Override;
use Sabatier\Foundation\Dictionary;

/** @internal */
final class BlockPredicate extends Predicate
{
    #[Override]
    public string $predicateFormat {
        get => "BLOCKPREDICATE()";
    }

    public function __construct(private readonly Closure $block)
    {
    }

    #[Override]
    public function withSubstitutionVariables(Dictionary $variables): Predicate
    {
        return $this;
    }

    #[Override]
    public function evaluate(mixed $object = null, ?Dictionary $substitutionVariables = null): bool
    {
        return (bool)($this->block)($object, $substitutionVariables);
    }

    #[Override]
    public function accept(PredicateVisitor $visitor, #[ExpectedValues(flagsFromClass: PredicateVisitorFlags::class)] int $flags): void
    {
        $visitor->visitPredicate($this);
    }
}
