<?php

namespace Sabatier\Foundation\Predicates;

/**
 * These constants describe the possible types of modifier for {@see ComparisonPredicate}.
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
