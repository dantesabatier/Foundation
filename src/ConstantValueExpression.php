<?php

/**
 * @author Dante Sabatier <dantesabatier@me.com>
 * @version 1.0
 * @package Sabatier\Foundation
 */

namespace Sabatier\Foundation;

/** @internal */
class ConstantValueExpression extends Expression
{
    private mixed $constantValue;

    public function __construct(mixed $value)
    {
        parent::__construct(ExpressionType::constantValue);
        $this->constantValue = is_string($value) ? (new Value($value))->value : $value;
    }

    public function expressionValue(mixed $object = null, ?Dictionary $context = null): mixed
    {
        $value = $this->constantValue;
        if ($value instanceof Expression) {
            $value = $value->expressionValue($object, $context);
        }
        if (Predicate::$debugLevel) {
            error_log(sprintf("Foundation: expression %s: %s", $this->expressionType->name, human_readable_value($value)));
        }
        return $value;
    }

    public function constantValue(): mixed
    {
        return $this->constantValue;
    }

    public function keyPath(): string
    {
        return $this->predicateFormat();
    }

    public function predicateFormat(): string
    {
        $constantValue = $this->constantValue;
        if (is_string($constantValue)) {
            return "'$constantValue'";
        }
        return human_readable_value($constantValue);
    }
}
