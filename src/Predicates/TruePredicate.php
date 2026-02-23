<?php

/**
 * Created by PhpStorm.
 * User: dante
 * Date: 27/06/20
 * Time: 01:32
 */

namespace Sabatier\Foundation\Predicates;

use JetBrains\PhpStorm\ExpectedValues;
use Override;
use Sabatier\Foundation\Dictionary;

/** @internal */
final class TruePredicate extends Predicate
{
    private static ?TruePredicate $default = null;
    #[Override]
    public string $predicateFormat {
        get => "TRUEPREDICATE";
    }

    public static function default(): TruePredicate
    {
        TruePredicate::$default ??= new TruePredicate();
        return TruePredicate::$default;
    }

    #[Override]
    public function evaluate(mixed $object = null, ?Dictionary $substitutionVariables = null): bool
    {
        return true;
    }

    #[Override]
    public function accept(PredicateVisitor $visitor, #[ExpectedValues(flagsFromClass: PredicateVisitorFlags::class)] int $flags): void
    {
        $visitor->visitPredicate($this);
    }
}
