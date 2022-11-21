<?php

namespace Sabatier\Foundation\Predicates;

/**
 * Defines the possible types of Expression
 */
enum ExpressionType: int
{
    case undefined = -1;
    /** An expression that always returns the same value. */
    case constantValue = 0;
    /** An expression that always returns the parameter object itself. */
    case evaluatedObject = 1;
    /** An expression that always returns whatever value is associated with the key specified by 'variable' in the bindings dictionary. */
    case variable = 2;
    /** An expression that returns something that can be used as a key path. */
    case keyPath = 3;
    /** An expression that returns the result of evaluating a function. */
    case function = 4;
    /** An expression that creates a union of the results of two nested expressions. */
    case unionSet = 5;
    /** An expression that creates an intersection of the results of two nested expressions. */
    case intersectSet = 6;
    /** An expression that combines two nested expression results by set subtraction. */
    case minusSet = 7;
    /** @internal */
    case keyPathSpecifierExpressionType = 10;
    /** @internal */
    case symbolic = 11;
    /** An expression that filters a collection using a subpredicate. */
    case subquery = 13;
    /** An expression that defines an aggregate of Expression objects. */
    case aggregate = 14;
    /** An expression that represents any key. */
    case anyKey = 15;
    /** @internal */
    case variableAssignment = 16;
    /** An expression that uses a Block. */
    case block = 19;
    case conditional = 20;
    /** @internal */
    case operator = 1001;
}
