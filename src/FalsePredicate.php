<?php

/**
 * Created by PhpStorm.
 * User: dante
 * Date: 27/06/20
 * Time: 01:32
 */

namespace Sabatier\Foundation;

use JetBrains\PhpStorm\ExpectedValues;
use JetBrains\PhpStorm\Pure;

/** @internal */
final class FalsePredicate extends Predicate
{
    private static ?FalsePredicate $default = null;

    public static function default(): FalsePredicate
    {
        if (FalsePredicate::$default === null) {
            FalsePredicate::$default = new FalsePredicate();
        }
        return FalsePredicate::$default;
    }

    public function evaluate(mixed $object = null, ?Dictionary $substitutionVariables = null): bool
    {
        return false;
    }

    public function accept(PredicateVisitor $visitor, #[ExpectedValues(flagsFromClass: PredicateVisitorFlags::class)] int $flags): void
    {
        $visitor->visitPredicate($this);
    }

    #[Pure]
    public function description(): string
    {
        return 'FALSEPREDICATE';
    }
}
