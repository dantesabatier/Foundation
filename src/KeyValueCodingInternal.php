<?php

namespace Sabatier\Foundation;

function components_from_key_path(string $keyPath): KeyPathComponents
{
    $parts = explode(".", $keyPath, 2);
    $key = $parts[0];
    $remainder = (isset($parts[1]) && $parts[1] !== "") ? $parts[1] : null;
    return new KeyPathComponents($key, $remainder);
}

function kvc_operator_from_key(string $key): ?string
{
    if ($key === "" || $key[0] !== "@") {
        return null;
    }
    $name = substring_from_index($key, 1);
    return match ($name) {
        KeyValueOperator::averageKeyValueOperator, KeyValueOperator::countKeyValueOperator, KeyValueOperator::maximumKeyValueOperator, KeyValueOperator::minimumKeyValueOperator, KeyValueOperator::sumKeyValueOperator, KeyValueOperator::medianKeyValueOperator, KeyValueOperator::modeKeyValueOperator, KeyValueOperator::standardDeviationKeyValueOperator, KeyValueOperator::distinctUnionOfArraysKeyValueOperator, KeyValueOperator::distinctUnionOfObjectsKeyValueOperator, KeyValueOperator::distinctUnionOfSetsKeyValueOperator, KeyValueOperator::unionOfArraysKeyValueOperator, KeyValueOperator::unionOfObjectsKeyValueOperator, KeyValueOperator::unionOfSetsKeyValueOperator => $name,
        default => null,
    };
}

/**
 * @param string $keyPath
 * @return array{string, string, string}
 */
function kvc_components(string $keyPath): array
{
    $idx = strpos($keyPath, "@");
    if ($idx !== false) {
        $pathPart = substring_to_index($keyPath, $idx);
        $operatorPart = substring_from_index($keyPath, $idx + 1);
    } else {
        $pathPart = $keyPath;
        $operatorPart = "";
    }
    $collection = "";
    $keyPathToProperty = "";
    $components = string_split_trimmed($pathPart, ".");
    if ($components !== []) {
        $collection = array_shift($components);
        if ($components !== []) {
            $keyPathToProperty = implode(".", $components);
        }
    }
    return [$collection, $operatorPart, $keyPathToProperty];
}
