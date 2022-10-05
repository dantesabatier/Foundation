<?php

/**
 * @author Dante Sabatier <dantesabatier@me.com>
 * @version 1.0
 * @package Sabatier\Foundation
 */

namespace Sabatier\Foundation;

/**
 * Class ComparisonPredicateOption
 * These constants describe the possible types of string comparison for {@see ComparisonPredicate}.
 * These options are supported for LIKE as well as all the equality/comparison operators.
 * @package Sabatier\Foundation
 */
class ComparisonPredicateOptions
{
    final const none = 0;
    /** @var int Case-insensitive. */
    final const caseInsensitive = 1;
    /** @var int Diacritic insensitive. */
    final const diacriticInsensitive = 2;
    /** @var int Indicates that the strings to be compared have been preprocessed. */
    final const normalized = 4;
    /** @var int Indicates that strings to be compared using <, <=, =, =>, > should be handled in a locale aware fashion. */
    final const localeSensitive = 8;
}
