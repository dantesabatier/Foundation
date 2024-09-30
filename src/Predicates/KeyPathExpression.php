<?php

namespace Sabatier\Foundation\Predicates;

use Override;
use Sabatier\Foundation\ArrayClass;
use Sabatier\Foundation\Dictionary;
use function Sabatier\Foundation\human_readable_value;
use function Sabatier\Foundation\typeof;

/** @internal */
class KeyPathExpression extends FunctionExpression
{
    public function __construct(public readonly mixed $keyPath, Expression $operand)
    {
        $selector = "valueForKeyPath";
        if ($this->keyPath instanceof KeyPathSpecifierExpression && !str_contains($this->keyPath->value, ".")) {
            $selector = "valueForKey";
        }
        parent::__construct(ExpressionType::keyPath, $operand, $selector, new ArrayClass([$this->keyPath]));
    }

    #[Override]
    public function withSubstitutionVariables(Dictionary $variables): Expression
    {
        return $this;
    }

    #[Override]
    public function expressionValue(mixed $object = null, ?Dictionary $context = null): mixed
    {
        $operand = $this->operand;
        $obj = $operand->expressionValue($object, $context);
        $selector = $this->selector;
        $keyPath = $this->keyPath;
        if (is_object($obj)) {
            $arguments = [(string)$keyPath];
            $value = $obj->$selector(...$arguments);
            if (Predicate::$debugDefault) {
                error_log(sprintf("Foundation: expression %s: %s::%s(%s) => %s", $this->expressionType->name, typeof($obj), $selector, implode(", ", $arguments), human_readable_value($value)));
            }
            return $value;
        }
        return $obj;
    }

    #[Override]
    public function keyPath(): string
    {
        return $this->keyPath;
    }

    #[Override]
    public function constantValue(): mixed
    {
        return $this->keyPath;
    }

    #[Override]
    public function predicateFormat(): string
    {
        $format = "";
        if (($operand = $this->operand()) && ($operand->expressionType !== ExpressionType::evaluatedObject)) {
            $format .= $operand->description();
            $format .= ".";
        }
        return $format . $this->keyPath;
    }
}
