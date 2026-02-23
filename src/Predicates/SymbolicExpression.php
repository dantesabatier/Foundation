<?php

namespace Sabatier\Foundation\Predicates;

use Override;
use Sabatier\Foundation\Dictionary;

/** @internal */
final class SymbolicExpression extends Expression
{
    #[\Override]
    public string $predicateFormat {
        get => $this->constantValue;
    }

    protected function __construct(string $token)
    {
        parent::__construct(ExpressionType::symbolic);
        $this->constantValue = $token;
    }

    #[Override]
    public function isEqual(mixed $other): bool
    {
        if ($other instanceof SymbolicExpression) {
            return $this->constantValue === $other->constantValue;
        }
        return false;
    }

    /** @noinspection PhpMixedReturnTypeCanBeReducedInspection */
    #[Override]
    public function expressionValue(mixed $object = null, ?Dictionary $context = null): mixed
    {
        return $this;
    }
}
