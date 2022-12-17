<?php

/**
 * Created by PhpStorm.
 * User: dante
 * Date: 24/07/20
 * Time: 14:12
 */

namespace Sabatier\Foundation\Predicates;

/** @internal */
class PredicateOperatorSymbol
{
    final const lessThan = "<";
    final const lessThanOrEqualTo = "<=";
    final const greaterThan = ">";
    final const greaterThanOrEqualTo = ">=";
    final const equalTo = "=";
    final const notEqualTo = "!=";
    final const matches = "MATCHES";
    final const like = "LIKE";
    final const beginsWith = "BEGINSWITH";
    final const endsWith = "ENDSWITH";
    final const in = "IN";
    final const contains = "CONTAINS";
    final const between = "BETWEEN";
}
