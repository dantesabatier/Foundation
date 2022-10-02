<?php

namespace Sabatier\Foundation;

use JetBrains\PhpStorm\Pure;

/** @internal */
class KeyPathSpecifierExpression extends Expression
{
    #[Pure]
    public function __construct(private readonly string $value)
    {
        parent::__construct(ExpressionType::keyPathSpecifierExpressionType);
    }

    public function expressionValue(mixed $object = null, ?Dictionary $context = null): mixed
    {
        return $this->value;
    }

    public function constantValue(): mixed
    {
        return $this->value;
    }

    public function keyPath(): string
    {
        return $this->value;
    }

    public function predicateFormat(): string
    {
        $format = '';
        $useDot = false;
        $components = explode('.', $this->keyPath());
        foreach ($components as $component) {
            if ($useDot) {
                $format .= '.';
            }
            if (PredicateUtilities::isReserved($component)) {
                $format .= '#';
            }
            $format .= $component;
            $useDot = true;
        }
        return $format;
    }

    public function isEqual(mixed $other): bool
    {
        if ($other instanceof KeyPathSpecifierExpression) {
            return string_is_equal($this->keyPath(), $other->keyPath());
        }
        return false;
    }
}
