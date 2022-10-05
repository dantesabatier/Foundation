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
    final const addition = '+';
    final const subtraction = '-';
    final const multiplication = '*';
    final const division = '/';
    final const modulo = '%';
    final const raiseToPower = '**';
    final const bitwiseAnd = '&';
    final const bitwiseOr = '|';
    final const bitwiseXor = '^';
    final const shiftLeft = '<<';
    final const shiftRight = '>>';
    final const assignment = ':=';
    final const bitwiseNot = '~';
}
