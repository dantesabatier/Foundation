<?php

namespace Sabatier\Foundation\Predicates;

/**
 * These constants describe the possible types of {@see CompoundPredicate}.
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
