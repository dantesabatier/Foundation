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
    final const string lessThan = "<";
    final const string lessThanOrEqualTo = "<=";
    final const string greaterThan = ">";
    final const string greaterThanOrEqualTo = ">=";
    final const string equalTo = "=";
    final const string notEqualTo = "!=";
    final const string matches = "MATCHES";
    final const string like = "LIKE";
    final const string beginsWith = "BEGINSWITH";
    final const string endsWith = "ENDSWITH";
    final const string in = "IN";
    final const string contains = "CONTAINS";
    final const string between = "BETWEEN";
}
