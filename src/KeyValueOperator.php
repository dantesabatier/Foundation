<?php

declare(strict_types=1);

namespace Sabatier\Foundation;

final class KeyValueOperator
{
    /** @var string The @count array operator. */
    const string countKeyValueOperator = "count";
    /** @var string The @avg array operator. */
    const string averageKeyValueOperator = "avg";
    /** @var string The @median array operator. */
    const string medianKeyValueOperator = "median";
    /** @var string The @mode array operator. */
    const string modeKeyValueOperator = "mode";
    /** @var string The @stddev array operator. */
    const string standardDeviationKeyValueOperator = "stddev";
    /** @var string The @distinctUnionOfArrays array operator. */
    const string distinctUnionOfArraysKeyValueOperator = "distinctUnionOfArrays";
    /** @var string The @distinctUnionOfObjects array operator. */
    const string distinctUnionOfObjectsKeyValueOperator = "distinctUnionOfObjects";
    /** @var string The @distinctUnionOfSets array operator. */
    const string distinctUnionOfSetsKeyValueOperator = "distinctUnionOfSets";
    /** @var string The @max array operator. */
    const string maximumKeyValueOperator = "max";
    /** @var string The @min array operator. */
    const string minimumKeyValueOperator = "min";
    /** @var string The @sum array operator. */
    const string sumKeyValueOperator = "sum";
    /** @var string The @unionOfArrays array operator. */
    const string unionOfArraysKeyValueOperator = "unionOfArrays";
    /** @var string The @unionOfObjects array operator. */
    const string unionOfObjectsKeyValueOperator = "unionOfObjects";
    /** @var string The @unionOfSets array operator. */
    const string unionOfSetsKeyValueOperator = "unionOfSets";
}
