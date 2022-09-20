<?php

namespace Sabatier\Foundation;

/** @internal */
function pv(mixed $v): mixed
{
    return $v instanceof Value ? $v->value : $v;
}

/** @internal */
function pn(string|int|float|Number $n): int|float
{
    if (is_string($n) && is_numeric($n)) {
        $n = new Number($n);
    }
    $v = pv($n);
    return (is_int($v) || is_float($v)) ? $v : (int)$v;
}

function equivalent(mixed $a, mixed $b): bool
{
    return $a instanceof Equatable ? $a->isEqual($b) : $a === $b;
}
