<?php

namespace Sabatier\Foundation\Predicates;

use Override;
use Sabatier\Foundation\Dictionary;

/** @internal */
class KeyPathSpecifierExpression extends Expression
{
    public string $predicateFormat {
        get {
            $format = "";
            $useDot = false;
            $components = explode(".", $this->keyPath);
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
    }

    public function __construct(string $value)
    {
        parent::__construct(ExpressionType::keyPathSpecifierExpressionType);
        $this->keyPath = $value;
        $this->constantValue = $value;
    }

    #[Override]
    public function expressionValue(mixed $object = null, ?Dictionary $context = null): string
    {
        return $this->keyPath;
    }

    #[Override]
    public function isEqual(mixed $other): bool
    {
        if ($other instanceof KeyPathSpecifierExpression) {
            return $this->keyPath === $other->keyPath;
        }
        return false;
    }
}
