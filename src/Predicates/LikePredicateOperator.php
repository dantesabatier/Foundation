<?php

declare(strict_types=1);

/**
 * Created by PhpStorm.
 * User: dante
 * Date: 24/07/20
 * Time: 13:57
 */
namespace Sabatier\Foundation\Predicates;

use Override;
use Sabatier\Foundation\CompareOptions;
use function Sabatier\Foundation\human_readable_value;
use function Sabatier\Foundation\string_matches;

/** @internal */
final class LikePredicateOperator extends MatchingPredicateOperator
{
    #[Override]
    protected function performPrimitiveOperation(mixed $left, mixed $right): bool
    {
        if ($left === null || $right === null) {
            return false;
        }
        assert(is_string($left) && is_string($right), sprintf("Cannot perform substring check on non-strings %s and %s", human_readable_value($left), human_readable_value($right)));
        // LIKE compares literally: no wildcard expands, in either syntax. It cannot inherit MATCHES' handling, which forces CompareOptions::quoted to tell string_search() the pattern is already escaped so a real regex survives — that is right for MATCHES and wrong here, because it lets a regex metacharacter through ("abc" LIKE "a.c" would hold) where the SQL side compares the dot literally. Clear the flag so the pattern is quoted and only the exact string matches.
        return string_matches($left, $right, $this->compareOptions & ~CompareOptions::quoted);
    }
}
