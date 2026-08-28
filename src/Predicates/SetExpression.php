<?php

declare(strict_types=1);

namespace Sabatier\Foundation\Predicates;

use JetBrains\PhpStorm\ExpectedValues;
use Override;
use Sabatier\Foundation\ArrayClass;
use Sabatier\Foundation\Dictionary;
use Sabatier\Foundation\Set;
use function Sabatier\Foundation\human_readable_value;

/** @internal */
final class SetExpression extends Expression
{
    #[Override]
    public string $predicateFormat {
        get => $this->left->predicateFormat . match ($this->expressionType) {
                ExpressionType::minusSet => " MINUS ",
                ExpressionType::intersectSet => " INTERSECT ",
                ExpressionType::unionSet => " UNION ",
                default => "",
            } . $this->right->predicateFormat;
    }

    public function __construct(ExpressionType $expressionType, Expression $left, Expression $right)
    {
        parent::__construct($expressionType);
        $this->left = $left;
        $this->right = $right;
    }

    #[Override]
    public function expressionValue(mixed $object = null, ?Dictionary $context = null): Set
    {
        $left = $this->left->expressionValue($object, $context) ?? new Set();
        if ($left instanceof ArrayClass) {
            /** @var Set $left */
            $left = new Set($left);
        }
        $right = $this->right->expressionValue($object, $context) ?? new Set();
        if ($right instanceof ArrayClass) {
            /** @var Set $right */
            $right = new Set($right);
        }
        $expressionType = $this->expressionType;
        // The operand is mutated in place, so the trace has to remember what it held: logging $left afterwards showed the result in the operand position too, and the line read "the result combined with the right operand gives the result".
        $operand = Predicate::$debugDefault ? human_readable_value($left) : "";
        if ($expressionType === ExpressionType::minusSet) {
            $left->subtract($right);
        } elseif ($expressionType === ExpressionType::intersectSet) {
            $left->formIntersection($right);
        } elseif ($expressionType === ExpressionType::unionSet) {
            $left->formUnion($right);
        }
        if (Predicate::$debugDefault) {
            Predicate::debug(sprintf("%s %s: %s %s => %s", $this->debugDescription, $expressionType->name, $operand, human_readable_value($right), human_readable_value($left)));
        }
        return $left;
    }

    #[Override]
    public function accept(PredicateVisitor $visitor, #[ExpectedValues(flagsFromClass: PredicateVisitorFlags::class)] int $flags): void
    {
        if (!($flags & PredicateVisitorFlags::expressions)) {
            return;
        }
        if ($flags & PredicateVisitorFlags::internalNodes) {
            $visitor->visitPredicateExpression($this);
        }
        $this->left->accept($visitor, $flags);
        $this->right->accept($visitor, $flags);
        if ($flags & PredicateVisitorFlags::internalNodes) {
            $visitor->visitPredicateExpression($this);
        }
    }
}
