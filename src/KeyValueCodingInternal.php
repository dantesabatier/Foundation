<?php

namespace Sabatier\Foundation;

function components_from_key_path(string $keyPath): KeyPathComponents
{
    $remainderPath = null;
    $idx = strpos($keyPath, ".");
    if ($idx !== false) {
        $remainderPath = "";
        $subKey = substring_to_index($keyPath, $idx);
        if ($idx < (strlen($keyPath) - 1)) {
            $remainderPath = substring_from_index($keyPath, $idx + 1);
        }
        $keyPath = $subKey;
    }
    return new KeyPathComponents($keyPath, $remainderPath);
}

function kvc_operator_from_key(string $key): ?string
{
    if ($key === "" || $key[0] !== "@") {
        return null;
    }
    $name = substring_from_index($key, 1);
    return match ($name) {
        KeyValueOperator::averageKeyValueOperator, KeyValueOperator::countKeyValueOperator, KeyValueOperator::distinctUnionOfArraysKeyValueOperator, KeyValueOperator::distinctUnionOfObjectsKeyValueOperator, KeyValueOperator::distinctUnionOfSetsKeyValueOperator, KeyValueOperator::maximumKeyValueOperator, KeyValueOperator::minimumKeyValueOperator, KeyValueOperator::sumKeyValueOperator, KeyValueOperator::unionOfArraysKeyValueOperator, KeyValueOperator::unionOfObjectsKeyValueOperator, KeyValueOperator::unionOfSetsKeyValueOperator => $name,
        default => null,
    };
}

/**
 * @param string $keyPath
 * @return string[]
 */
function kvc_components(string $keyPath): array
{
    $idx = strpos($keyPath, "@");
    if ($idx === false) {
        return [];
    }
    $collection = "";
    $property = "";
    /** @var string[] $components */
    $components = preg_split(sprintf("/%s/", preg_quote(".", "/")), substring_to_index($keyPath, $idx), -1, PREG_SPLIT_NO_EMPTY);
    $numberOfComponents = count($components);
    if ($numberOfComponents) {
        $collection = array_shift($components);
        if ($numberOfComponents > 1) {
            $property = array_pop($components);
        }
    }
    return [$collection, substring_from_index($keyPath, $idx + 1), $property];
}
