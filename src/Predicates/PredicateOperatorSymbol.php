<?php

declare(strict_types=1);

/**
 * Created by PhpStorm.
 * User: dante
 * Date: 24/07/20
 * Time: 14:12
 */
namespace Sabatier\Foundation\Predicates;

/** @internal */
final class PredicateOperatorSymbol
{
    const string lessThan = "<";
    const string lessThanOrEqualTo = "<=";
    const string greaterThan = ">";
    const string greaterThanOrEqualTo = ">=";
    const string equalTo = "=";
    const string notEqualTo = "!=";
    const string matches = "MATCHES";
    const string like = "LIKE";
    const string beginsWith = "BEGINSWITH";
    const string endsWith = "ENDSWITH";
    const string in = "IN";
    const string contains = "CONTAINS";
    const string between = "BETWEEN";
}
