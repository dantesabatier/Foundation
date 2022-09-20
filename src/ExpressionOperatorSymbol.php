<?php
/**
 * Created by PhpStorm.
 * User: dante
 * Date: 12/11/20
 * Time: 13:27
 */

namespace Sabatier\Foundation;

/** @internal */
class ExpressionOperatorSymbol
{
    const addition = '+';
    const subtraction = '-';
    const multiplication = '*';
    const division = '/';
    const modulo = '%';
    const raiseToPower = '**';
    const bitwiseAnd = '&';
    const bitwiseOr = '|';
    const bitwiseXor = '^';
    const shiftLeft = '<<';
    const shiftRight = '>>';
    const assignment = ':=';
    const bitwiseNot = '~';
}