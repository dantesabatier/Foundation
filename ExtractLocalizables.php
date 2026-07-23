<?php

declare(strict_types=1);

require_once __DIR__ . "/vendor/autoload.php";

use Sabatier\Foundation\ArrayClass;
use Sabatier\Foundation\Bundle;
use Sabatier\Foundation\FileManager;
use Sabatier\Foundation\LocalizationExtractor;

try {
    $bundle = Bundle::bundleForClass(FileManager::class);
    $excludedFilenames = new ArrayClass(["ArrayClass", "IndexPath", "Set", "AggregateExpression", "ExpressionOperator", "CollectionDifference"]);
    $extractor = new LocalizationExtractor($bundle, new ArrayClass(["en", "es"]), $excludedFilenames);
    $extractor->extract();
} catch (Exception $exception) {
    error_log("Exception raised $exception");
}
