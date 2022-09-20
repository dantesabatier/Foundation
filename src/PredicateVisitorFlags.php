<?php
/**
 * Created by PhpStorm.
 * User: dante
 * Date: 24/07/20
 * Time: 09:44
 */

namespace Sabatier\Foundation;

/** @internal */
class PredicateVisitorFlags
{
    const expressions = 1;
    const operators = 2;
    const internalNodes = 4;
    const operatorsBefore = 8;
    const common = PredicateVisitorFlags::expressions | PredicateVisitorFlags::operators | PredicateVisitorFlags::internalNodes;
}
