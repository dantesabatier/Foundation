<?php

declare(strict_types=1);

namespace Sabatier\Foundation\Predicates;

/**
 * These constants describe the possible types of string comparison for {@see ComparisonPredicate}.
 * These options are supported for LIKE as well as all the equality/comparison operators.
 */
final class ComparisonPredicateOptions
{
    const int none = 0;
    /** @var int Case-insensitive. */
    const int caseInsensitive = 1;
    /** @var int Diacritic insensitive. */
    const int diacriticInsensitive = 2;
    /** @var int Indicates that the strings to be compared have been preprocessed. */
    const int normalized = 4;
    /** @var int Indicates that strings to be compared using <, <=, =, =>, > should be handled in a locale aware fashion. */
    const int localeSensitive = 8;
}
