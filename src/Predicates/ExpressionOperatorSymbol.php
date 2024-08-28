<?php

/**
 * Created by PhpStorm.
 * User: dante
 * Date: 12/11/20
 * Time: 13:27
 */

namespace Sabatier\Foundation\Predicates;

/** @internal */
class ExpressionOperatorSymbol
{
    final const string addition = "+";
    final const string subtraction = "-";
    final const string multiplication = "*";
    final const string division = "/";
    final const string modulo = "%";
    final const string raiseToPower = "**";
    final const string bitwiseAnd = "&";
    final const string bitwiseOr = "|";
    final const string bitwiseXor = "^";
    final const string shiftLeft = "<<";
    final const string shiftRight = ">>";
    final const string assignment = ":=";
    final const string bitwiseNot = "~";
}
