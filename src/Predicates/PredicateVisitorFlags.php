<?php

/**
 * Created by PhpStorm.
 * User: dante
 * Date: 24/07/20
 * Time: 09:44
 */

namespace Sabatier\Foundation\Predicates;

/** @internal */
class PredicateVisitorFlags
{
    final const int expressions = 1;
    final const int operators = 2;
    final const int internalNodes = 4;
    final const int operatorsBefore = 8;
    final const int common = PredicateVisitorFlags::expressions | PredicateVisitorFlags::operators | PredicateVisitorFlags::internalNodes;
    final const int all = PredicateVisitorFlags::common | PredicateVisitorFlags::operatorsBefore;
}
