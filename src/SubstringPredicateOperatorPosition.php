<?php
/**
 * Created by PhpStorm.
 * User: dante
 * Date: 24/07/20
 * Time: 15:39
 */

namespace Sabatier\Foundation;

/** @internal */
enum SubstringPredicateOperatorPosition: int
{
    case beginsWith = 0;
    case endsWith = 1;
    case contains = 2;
}
