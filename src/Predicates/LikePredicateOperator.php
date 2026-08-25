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
        // LIKE takes a wildcard pattern, not a regular expression: "*" stands for any sequence and "?" for a single character, and every other character is literal ("a.c" LIKE "a.c" holds while "abc" LIKE "a.c" does not). Quote the pattern so the regex metacharacters inside it lose their meaning, then reinstate the two wildcards, and pass it as already quoted so string_search() does not escape them right back. It anchors the pattern itself for SearchMethod::matches.
        $pattern = strtr(preg_quote($right, "/"), ["\\*" => ".*", "\\?" => "."]);
        return string_matches($left, $pattern, $this->compareOptions | CompareOptions::quoted);
    }
}
