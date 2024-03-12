<?php

namespace Sabatier\Foundation;

class KeyValueOperator
{
    /** @var string The @avg array operator. */
    final const averageKeyValueOperator = "avg";
    /** @var string The @count array operator. */
    final const countKeyValueOperator = "count";
    final const medianKeyValueOperator = "median";
    final const modeKeyValueOperator = "mode";
    final const standardDeviationKeyValueOperator = "stddev";
    /** @var string The @distinctUnionOfArrays array operator. */
    final const distinctUnionOfArraysKeyValueOperator = "distinctUnionOfArrays";
    /** @var string The @distinctUnionOfObjects array operator. */
    final const distinctUnionOfObjectsKeyValueOperator = "distinctUnionOfObjects";
    /** @var string The @distinctUnionOfSets array operator. */
    final const distinctUnionOfSetsKeyValueOperator = "distinctUnionOfSets";
    /** @var string The @max array operator. */
    final const maximumKeyValueOperator = "max";
    /** @var string The @min array operator. */
    final const minimumKeyValueOperator = "min";
    /** @var string The @sum array operator. */
    final const sumKeyValueOperator = "sum";
    /** @var string The @unionOfArrays array operator. */
    final const unionOfArraysKeyValueOperator = "unionOfArrays";
    /** @var string The @unionOfObjects array operator. */
    final const unionOfObjectsKeyValueOperator = "unionOfObjects";
    /** @var string The @unionOfSets array operator. */
    final const unionOfSetsKeyValueOperator = "unionOfSets";
}
