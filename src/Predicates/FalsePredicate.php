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
final class FalsePredicate extends Predicate
{
    private static ?FalsePredicate $default = null;
    #[\Override]
    public string $predicateFormat {
        get => "FALSEPREDICATE";
    }

    public static function default(): FalsePredicate
    {
        FalsePredicate::$default ??= new FalsePredicate();
        return FalsePredicate::$default;
    }

    #[Override]
    public function evaluate(mixed $object = null, ?Dictionary $substitutionVariables = null): bool
    {
        return false;
    }

    #[Override]
    public function accept(PredicateVisitor $visitor, #[ExpectedValues(flagsFromClass: PredicateVisitorFlags::class)] int $flags): void
    {
        $visitor->visitPredicate($this);
    }
}
