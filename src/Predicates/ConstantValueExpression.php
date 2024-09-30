<?php

namespace Sabatier\Foundation\Predicates;

use Override;
use Sabatier\Foundation\Dictionary;
use Sabatier\Foundation\Value;
use function Sabatier\Foundation\human_readable_value;

/** @internal */
class ConstantValueExpression extends Expression
{
    private readonly mixed $constantValue;

    public function __construct(mixed $value)
    {
        parent::__construct(ExpressionType::constantValue);
        $this->constantValue = is_string($value) ? (new Value($value))->value : $value;
    }

    #[Override]
    public function expressionValue(mixed $object = null, ?Dictionary $context = null): mixed
    {
        $value = $this->constantValue;
        if ($value instanceof Expression) {
            $value = $value->expressionValue($object, $context);
        }
        if (Predicate::$debugDefault) {
            error_log(sprintf("Foundation: expression %s: %s", $this->expressionType->name, human_readable_value($value)));
        }
        return $value;
    }

    #[Override]
    public function constantValue(): mixed
    {
        return $this->constantValue;
    }

    #[Override]
    public function keyPath(): string
    {
        return $this->predicateFormat();
    }

    #[Override]
    public function predicateFormat(): string
    {
        $constantValue = $this->constantValue;
        if (is_string($constantValue)) {
            return "'$constantValue'";
        }
        return human_readable_value($constantValue);
    }
}
