<?php

declare(strict_types=1);

namespace Sabatier\Foundation\Predicates;

use Override;
use Sabatier\Foundation\ArrayClass;
use Sabatier\Foundation\Dictionary;
use function Sabatier\Foundation\human_readable_value;
use function Sabatier\Foundation\typeof;

/** @internal */
final class KeyPathExpression extends FunctionExpression
{
    #[Override]
    public string $predicateFormat {
        get {
            $format = "";
            if (($operand = $this->operand) && ($operand->expressionType !== ExpressionType::evaluatedObject)) {
                $format .= $operand->description;
                $format .= ".";
            }
            return $format . $this->keyPath;
        }
    }

    public function __construct(mixed $keyPath, Expression $operand)
    {
        $selector = "valueForKeyPath";
        if ($keyPath instanceof KeyPathSpecifierExpression || $keyPath instanceof KeyPathExpression) {
            $keyPath = $keyPath->keyPath;
            if (!str_contains($keyPath, ".")) {
                $selector = "valueForKey";
            }
        }
        parent::__construct(ExpressionType::keyPath, $operand, $selector, new ArrayClass([$keyPath]));
        $this->keyPath = $keyPath;
        $this->constantValue = $keyPath;
        $this->arguments = new ArrayClass();
    }

    #[Override]
    public function withSubstitutionVariables(Dictionary $variables): Expression
    {
        return $this;
    }

    #[Override]
    public function expressionValue(mixed $object = null, ?Dictionary $context = null): mixed
    {
        $obj = $this->operand?->expressionValue($object, $context);
        if (is_object($obj)) {
            $selector = $this->selector;
            $arguments = [$this->keyPath];
            $value = $obj->$selector(...$arguments);
            if (Predicate::$debugDefault) {
                error_log(sprintf("Foundation: %s %s: %s::%s(%s) => %s", $this->debugDescription, $this->expressionType->name, typeof($obj), $selector, implode(", ", $arguments), human_readable_value($value)));
            }
            return $value;
        }
        return $obj;
    }
}
