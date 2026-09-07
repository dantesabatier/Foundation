<?php

declare(strict_types=1);

namespace Sabatier\Foundation;

/** @internal */
function components_from_key_path(string $keyPath): KeyPathComponents
{
    $parts = explode(".", $keyPath, 2);
    $key = $parts[0];
    $remainder = (isset($parts[1]) && $parts[1] !== "") ? $parts[1] : null;
    return new KeyPathComponents($key, $remainder);
}

/** @internal */
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
 * @internal
 * @return array{string, string, string}
 */
function kvc_components(string $keyPath): array
{
    $pathPart = $keyPath;
    $operatorPart = "";
    $index = strpos($keyPath, "@");
    if ($index !== false) {
        $pathPart = substring_to_index($keyPath, $index);
        $operatorPart = substring_from_index($keyPath, $index + 1);
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
