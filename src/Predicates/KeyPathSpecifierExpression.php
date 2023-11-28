<?php

namespace Sabatier\Foundation\Predicates;

use Sabatier\Foundation\Dictionary;

/** @internal */
class KeyPathSpecifierExpression extends Expression
{
    public function __construct(public readonly string $value)
    {
        parent::__construct(ExpressionType::keyPathSpecifierExpressionType);
    }

    public function expressionValue(mixed $object = null, ?Dictionary $context = null): string
    {
        return $this->value;
    }

    public function constantValue(): string
    {
        return $this->value;
    }

    public function keyPath(): string
    {
        return $this->value;
    }

    public function predicateFormat(): string
    {
        $format = "";
        $useDot = false;
        $components = explode(".", $this->value);
        foreach ($components as $component) {
            if ($useDot) {
                $format .= ".";
            }
            if (PredicateUtilities::isReserved($component)) {
                $format .= "#";
            }
            $format .= $component;
            $useDot = true;
        }
        return $format;
    }

    public function isEqual(mixed $other): bool
    {
        if ($other instanceof KeyPathSpecifierExpression) {
            return $this->value === $other->value;
        }
        return false;
    }
}
