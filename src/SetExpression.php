<?php

/**
 * @author Dante Sabatier <dantesabatier@me.com>
 * @version 1.0
 * @package Sabatier\Foundation
 */

namespace Sabatier\Foundation;

use JetBrains\PhpStorm\ExpectedValues;
use JetBrains\PhpStorm\Pure;

/** @internal */
class SetExpression extends Expression
{
    #[Pure]
    public function __construct(ExpressionType $expressionType, private readonly Expression $leftExpression, private readonly Expression $rightExpression)
    {
        parent::__construct($expressionType);
    }

    public function expressionValue(mixed $object = null, ?Dictionary $context = null): mixed
    {
        $left = $this->left()->expressionValue($object, $context) ?? new Set();
        if ($left instanceof ArrayClass) {
            $left = new Set($left);
        }
        $right = $this->right()->expressionValue($object, $context) ?? new Set();
        if ($right instanceof ArrayClass) {
            $right = new Set($right);
        }
        $value = $left;
        $expressionType = $this->expressionType;
        if ($expressionType == ExpressionType::minusSet) {
            $value->subtract($right);
        } elseif ($expressionType == ExpressionType::intersectSet) {
            $value->formIntersection($right);
        } elseif ($expressionType == ExpressionType::unionSet) {
            $value->formUnion($right);
        }
        if (Predicate::$debugDefault) {
            error_log(sprintf("Foundation: expression %s %s %s => %s", human_readable_value($left), $expressionType->name, human_readable_value($right), human_readable_value($value)));
        }
        return $value;
    }

    public function accept(PredicateVisitor $visitor, #[ExpectedValues(flagsFromClass: PredicateVisitorFlags::class)] int $flags): void
    {
        if (!($flags & PredicateVisitorFlags::expressions)) {
            return;
        }
        if ($flags & PredicateVisitorFlags::internalNodes) {
            $visitor->visitPredicateExpression($this);
        }
        $this->left()->accept($visitor, $flags);
        $this->right()->accept($visitor, $flags);
        if ($flags & PredicateVisitorFlags::internalNodes) {
            $visitor->visitPredicateExpression($this);
        }
    }

    public function left(): Expression
    {
        return $this->leftExpression;
    }

    public function right(): Expression
    {
        return $this->rightExpression;
    }

    public function predicateFormat(): string
    {
        $leftExpression = $this->left();
        $rightExpression = $this->right();
        $format = $leftExpression->description();
        switch ($this->expressionType) {
            case ExpressionType::minusSet:
                $format .= ' MINUS ';
                break;
            case ExpressionType::intersectSet:
                $format .= ' INTERSECT ';
                break;
            case ExpressionType::unionSet:
                $format .= ' UNION ';
                break;
            default:
                break;
        }
        return $format . $rightExpression->description();
    }
}
