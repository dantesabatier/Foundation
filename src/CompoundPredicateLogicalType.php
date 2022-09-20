<?php

/**
 * @author Dante Sabatier <dantesabatier@me.com>
 * @version 1.0
 * @package Sabatier\Foundation
 */

namespace Sabatier\Foundation;

/**
 * Class CompoundPredicateLogicalType
 * These constants describe the possible types of {@see CompoundPredicate}.
 * @package Sabatier\Foundation
 */
enum CompoundPredicateLogicalType: int
{
    /** A logical NOT predicate. */
    case not = 0;
    /** A logical AND predicate. */
    case and = 1;
    /** A logical OR predicate. */
    case or = 2;
}
