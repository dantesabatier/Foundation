<?php

/**
 * Created by PhpStorm.
 * User: dante
 * Date: 26/06/20
 * Time: 23:50
 */

namespace Sabatier\Foundation\Predicates;

use Closure;
use JetBrains\PhpStorm\ExpectedValues;
use Sabatier\Foundation\ArrayClass;
use Sabatier\Foundation\Dictionary;
use function Sabatier\Foundation\human_readable_value;

/** @internal */
class BlockExpression extends Expression
{
    /**
     * @param Closure(mixed, ArrayClass<Expression>, Dictionary|null): mixed $block
     * @param ArrayClass<Expression>|null $arguments
     */

    public function __construct(private readonly Closure $block, private readonly ?ArrayClass $arguments = null)
    {
        parent::__construct(ExpressionType::block);
    }

    public function withSubstitutionVariables(Dictionary $variables): Expression
    {
        return new BlockExpression($this->block, $this->arguments?->map(fn(Expression $expression): Expression => $expression->withSubstitutionVariables($variables)));
    }

    public function expressionValue(mixed $object = null, ?Dictionary $context = null): mixed
    {
        $arguments = $this->arguments?->map(fn(Expression $expression): mixed => $expression->expressionValue($object, $context)) ?? new ArrayClass();
        $value = ($this->block)($object, $arguments, $context);
        if (Predicate::$debugDefault) {
            error_log(sprintf("Foundation: expression %s: function(%s) => %s", $this->expressionType->name, $arguments->join(", "), human_readable_value($value)));
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
        if ($arguments = $this->arguments) {
            foreach ($arguments as $argument) {
                $argument->accept($visitor, $flags);
            }
        }
        if ($flags & PredicateVisitorFlags::internalNodes) {
            $visitor->visitPredicateExpression($this);
        }
    }

    public function expressionBlock(): Closure
    {
        return $this->block;
    }

    public function arguments(): ?ArrayClass
    {
        return $this->arguments;
    }

    public function predicateFormat(): string
    {
        $format = "BLOCK(function";
        if ($arguments = $this->arguments) {
            if (!$arguments->isEmpty) {
                $format .= ", ";
            }
            $format .= $arguments->join(", ");
        }
        return $format . ")";
    }
}
