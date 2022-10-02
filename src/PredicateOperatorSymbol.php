<?php

/**
 * Created by PhpStorm.
 * User: dante
 * Date: 24/07/20
 * Time: 14:12
 */

namespace Sabatier\Foundation;

/** @internal */
class PredicateOperatorSymbol
{
    const lessThan = '<';
    const lessThanOrEqualTo = '<=';
    const greaterThan = '>';
    const greaterThanOrEqualTo = '>=';
    const equalTo = '=';
    const notEqualTo = '!=';
    const matches = 'MATCHES';
    const like = 'LIKE';
    const beginsWith = 'BEGINSWITH';
    const endsWith = 'ENDSWITH';
    const in = 'IN';
    const contains = 'CONTAINS';
    const between = 'BETWEEN';
}
