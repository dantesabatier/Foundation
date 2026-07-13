<?php

/**
 * Created by PhpStorm.
 * User: dante
 * Date: 26/06/20
 * Time: 23:50
 */

declare(strict_types=1);

namespace Sabatier\Foundation\Predicates;

use Closure;
use JetBrains\PhpStorm\ExpectedValues;
use Override;
use Sabatier\Foundation\ArrayClass;
use Sabatier\Foundation\Dictionary;
use function Sabatier\Foundation\human_readable_value;

/** @internal */
final class BlockExpression extends Expression
{
    #[Override]
    public string $predicateFormat {
        get {
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

    /**
     * @param Closure(mixed, ArrayClass<Expression>, Dictionary<mixed>|null): mixed $expressionBlock
     * @param ArrayClass<Expression>|null $arguments
     */

    public function __construct(Closure $expressionBlock, ?ArrayClass $arguments = null)
    {
        parent::__construct(ExpressionType::block);
        $this->arguments = $arguments;
        $this->expressionBlock = $expressionBlock;
    }

    #[Override]
    public function withSubstitutionVariables(Dictionary $variables): Expression
    {
        return new BlockExpression($this->expressionBlock, $this->arguments?->map(fn(Expression $expression): Expression => $expression->withSubstitutionVariables($variables)));
    }

    #[Override]
    public function expressionValue(mixed $object = null, ?Dictionary $context = null): mixed
    {
        $arguments = $this->arguments?->map(fn(Expression $expression): mixed => $expression->expressionValue($object, $context)) ?? new ArrayClass();
        $value = ($this->expressionBlock)($object, $arguments, $context);
        if (Predicate::$debugDefault) {
            error_log(sprintf("Foundation: %s %s: function(%s) => %s", $this->debugDescription, $this->expressionType->name, $arguments->join(", "), human_readable_value($value)));
        }
        return $value;
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
        if ($arguments = $this->arguments) {
            foreach ($arguments as $argument) {
                $argument->accept($visitor, $flags);
            }
        }
        if ($flags & PredicateVisitorFlags::internalNodes) {
            $visitor->visitPredicateExpression($this);
        }
    }
}
