<?php

namespace Sabatier\Foundation;

use JetBrains\PhpStorm\Pure;

/** @internal */
#[Pure]
function components_from_key_path(string $key): KeyPathComponents
{
    $remainderPath = null;
    $idx = strpos($key, '.');
    if ($idx !== false) {
        $remainderPath = '';
        $subkey = substr($key, 0, $idx);
        if ($idx < strlen($key) - 1) {
            $remainderPath = substring_from_index($key, $idx + 1);
        }
        $key = $subkey;
    }
    return new KeyPathComponents($key, $remainderPath);
}

/** @internal */
function kvc_operator_from_key(string $key): ?string
{
    if (!string_has_prefix($key, '@')) {
        return null;
    }
    $name = substring_from_index($key, 1);
    return match ($name) {
        KeyValueOperator::averageKeyValueOperator, KeyValueOperator::countKeyValueOperator, KeyValueOperator::distinctUnionOfArraysKeyValueOperator, KeyValueOperator::distinctUnionOfObjectsKeyValueOperator, KeyValueOperator::distinctUnionOfSetsKeyValueOperator, KeyValueOperator::maximumKeyValueOperator, KeyValueOperator::minimumKeyValueOperator, KeyValueOperator::sumKeyValueOperator, KeyValueOperator::unionOfArraysKeyValueOperator, KeyValueOperator::unionOfObjectsKeyValueOperator, KeyValueOperator::unionOfSetsKeyValueOperator => $name,
        default => null,
    };
}