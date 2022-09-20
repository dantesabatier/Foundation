<?php

/**
 * @author Dante Sabatier <dantesabatier@me.com>
 * @version 1.0
 * @package Sabatier\Foundation
 */

namespace Sabatier\Foundation;

/** @internal */
class KeyPathExpression extends FunctionExpression
{
    public function __construct(private readonly mixed $keyPath, Expression $operand)
    {
        $selector = 'valueForKeyPath';
        if ($this->keyPath instanceof KeyPathSpecifierExpression && !string_contains($this->keyPath->keyPath(), '.')) {
            $selector = 'valueForKey';
        }
        parent::__construct(ExpressionType::keyPath, $operand, $selector, new ArrayClass([$keyPath]));
    }

    public function withSubstitutionVariables(Dictionary $variables): Expression
    {
        return $this;
    }

    public function expressionValue(mixed $object = null, ?Dictionary $context = null): mixed
    {
        $operand = $this->operand;
        $obj = $operand->expressionValue($object, $context);
        $selector = $this->selector;
        $keyPath = $this->keyPath;
        if (is_object($obj)) {
            $arguments = new ArrayClass([$keyPath]);
            $value = $obj->$selector(...$arguments);
            if (Predicate::$debugLevel) {
                error_log(sprintf("Foundation: expression %s: %s::%s(%s) => %s", $this->expressionType->name, typeof($obj), $selector, $arguments->join(", "), human_readable_value($value)));
            }
            return $value;
        }
        return $obj;
    }

    public function keyPath(): string
    {
        return $this->keyPath;
    }

    public function constantValue(): mixed
    {
        return $this->keyPath;
    }

    public function predicateFormat(): string
    {
        $format = '';
        if (($operand = $this->operand()) && ($operand->expressionType !== ExpressionType::evaluatedObject)) {
            $format .= $operand->description();
            $format .= '.';
        }
        $format .= $this->keyPath;
        return $format;
    }
}
