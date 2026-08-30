<?php

declare(strict_types=1);

/**
 * Created by PhpStorm.
 * User: dante
 * Date: 12/11/20
 * Time: 13:27
 */
namespace Sabatier\Foundation\Predicates;

/** @internal */
final class ExpressionOperatorSymbol
{
    const string addition = "+";
    const string subtraction = "-";
    const string multiplication = "*";
    const string division = "/";
    const string modulo = "%";
    const string raiseToPower = "**";
    const string bitwiseAnd = "&";
    const string bitwiseOr = "|";
    const string bitwiseXor = "^";
    const string shiftLeft = "<<";
    const string shiftRight = ">>";
    const string assignment = ":=";
    const string bitwiseNot = "~";
}
