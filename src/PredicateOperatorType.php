<?php

/**
 * Created by PhpStorm.
 * User: dante
 * Date: 24/07/20
 * Time: 10:53
 */

namespace Sabatier\Foundation;

/**
 * Class PredicateOperatorType
 * Defines the type of comparison for {@see ComparisonPredicate}.
 * @package Sabatier\Foundation
 */
enum PredicateOperatorType: int
{
    /** A less-than predicate. */
    case lessThan = 0;
    /** A less-than-or-equal-to predicate. */
    case lessThanOrEqualTo = 1;
    /** A greater-than predicate. */
    case greaterThan = 2;
    /** A greater-than-or-equal-to predicate. */
    case greaterThanOrEqualTo = 3;
    /** An equal-to predicate. */
    case equalTo = 4;
    /** A not-equal-to predicate. */
    case notEqualTo = 5;
    /** A full regular expression matching predicate. */
    case matches = 6;
    /** A simple subset of the MATCHES predicate, similar in behavior to SQL LIKE. */
    case like = 7;
    /** A begins-with predicate. */
    case beginsWith = 8;
    /** An ends-with predicate. */
    case endsWith = 9;
    /** A predicate to determine if the left hand side is in the right hand side. For strings, returns true if the left hand side is a substring of the right hand side . For collections, returns true if the left hand side is in the right hand side . */
    case in = 10;
    /** A predicate that uses a custom selector that takes a single argument and returns a BOOL value. The selector is invoked on the left hand side with the right hand side as the argument. */
    case customSelector = 11;
    /** A predicate to determine if the left hand side contains the right hand side. Returns true if [lhs contains rhs]; the left hand side must be an NSExpression object that evaluates to a collection */
    case contains = 99;
    /** A predicate to determine if the left hand side lies at or between bounds specified by the right hand side. Returns true if [lhs between rhs]; the right hand side must be an array in which the first element sets the lower bound and the second element the upper, inclusive. Comparison is performed using {@see Comparable::compare()} or the class-appropriate equivalent. */
    case between = 100;
}
