<?php

declare(strict_types=1);

/**
 * Created by PhpStorm.
 * User: dante
 * Date: 24/07/20
 * Time: 09:44
 */
namespace Sabatier\Foundation\Predicates;

final class PredicateVisitorFlags
{
    const int expressions = 1;
    const int operators = 2;
    const int internalNodes = 4;
    const int operatorsBefore = 8;
    const int common = PredicateVisitorFlags::expressions | PredicateVisitorFlags::operators | PredicateVisitorFlags::internalNodes;
    const int all = PredicateVisitorFlags::common | PredicateVisitorFlags::operatorsBefore;
}
