<?php

namespace Sabatier\Foundation\Predicates;

use Override;
use Sabatier\Foundation\Dictionary;
use Sabatier\Foundation\Value;
use function Sabatier\Foundation\human_readable_value;

/** @internal */
final class ConstantValueExpression extends Expression
{
    public string $predicateFormat {
        get {
            $constantValue = $this->constantValue;
            if (is_string($constantValue)) {
                return "'$constantValue'";
            }
            return human_readable_value($constantValue);
        }
    }
    public string $keyPath {
        get => $this->keyPath ??= $this->predicateFormat;
    }

    public function __construct(mixed $value)
    {
        parent::__construct(ExpressionType::constantValue);
        $this->constantValue = is_string($value) ? new Value($value)->value : $value;
    }

    #[Override]
    public function expressionValue(mixed $object = null, ?Dictionary $context = null): mixed
    {
        $value = $this->constantValue;
        if ($value instanceof Expression) {
            $value = $value->expressionValue($object, $context);
        }
        if (Predicate::$debugDefault) {
            error_log(sprintf("Foundation: %s %s: %s", $this->debugDescription, $this->expressionType->name, human_readable_value($value)));
        }
        return $value;
    }
}
