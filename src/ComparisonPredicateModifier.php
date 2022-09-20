<?php

/**
 * @author Dante Sabatier <dantesabatier@me.com>
 * @version 1.0
 * @package Sabatier\Foundation
 */

namespace Sabatier\Foundation;

/**
 * Class ComparisonPredicateModifier
 * These constants describe the possible types of modifier for {@see ComparisonPredicate}.
 * @package Sabatier\Foundation
 */
enum ComparisonPredicateModifier: int
{
    /** A predicate to compare directly the left and right hand sides. */
    case direct = 0;
    /** A predicate to compare all entries in the destination of a to-many relationship. */
    case all = 1;
    /** A predicate to match with any entry in the destination of a to-many relationship. */
    case any = 2;
}
