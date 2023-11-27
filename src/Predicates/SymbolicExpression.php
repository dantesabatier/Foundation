<?php

namespace Sabatier\Foundation\Predicates;

use Sabatier\Foundation\Dictionary;

/** @internal */
class SymbolicExpression extends Expression
{
    protected function __construct(private readonly string $token)
    {
        parent::__construct(ExpressionType::symbolic);
    }

    public function isEqual(mixed $other): bool
    {
        if ($other instanceof SymbolicExpression) {
            return $this->token === $other->token;
        }
        return false;
    }

    public function constantValue(): string
    {
        return $this->token;
    }

    public function predicateFormat(): string
    {
        return $this->token;
    }

    /** @noinspection PhpMixedReturnTypeCanBeReducedInspection */
    public function expressionValue(mixed $object = null, ?Dictionary $context = null): mixed
    {
        return $this;
    }
}
