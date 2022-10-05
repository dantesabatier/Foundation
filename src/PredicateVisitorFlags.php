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
    final const expressions = 1;
    final const operators = 2;
    final const internalNodes = 4;
    final const operatorsBefore = 8;
    final const common = PredicateVisitorFlags::expressions | PredicateVisitorFlags::operators | PredicateVisitorFlags::internalNodes;
}
