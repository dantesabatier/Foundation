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
final class TruePredicate extends Predicate
{
    private static ?TruePredicate $default = null;

    public static function default(): TruePredicate
    {
        if (TruePredicate::$default === null) {
            TruePredicate::$default = new TruePredicate();
        }
        return TruePredicate::$default;
    }

    public function evaluate(mixed $object = null, ?Dictionary $substitutionVariables = null): bool
    {
        return true;
    }

    public function accept(PredicateVisitor $visitor, #[ExpectedValues(flagsFromClass: PredicateVisitorFlags::class)] int $flags): void
    {
        $visitor->visitPredicate($this);
    }

    #[Pure]
    public function description(): string
    {
        return 'TRUEPREDICATE';
    }
}
