<?php

namespace Sabatier\Foundation;

class KeyValueOperator
{
    /** @var string The @avg array operator. */
    const averageKeyValueOperator = 'avg';
    /** @var string The @count array operator. */
    const countKeyValueOperator = 'count';
    /** @var string The @distinctUnionOfArrays array operator. */
    const distinctUnionOfArraysKeyValueOperator = 'distinctUnionOfArrays';
    /** @var string The @distinctUnionOfObjects array operator. */
    const distinctUnionOfObjectsKeyValueOperator = 'distinctUnionOfObjects';
    /** @var string The @distinctUnionOfSets array operator. */
    const distinctUnionOfSetsKeyValueOperator = 'distinctUnionOfSets';
    /** @var string The @max array operator. */
    const maximumKeyValueOperator = 'max';
    /** @var string The @min array operator. */
    const minimumKeyValueOperator = 'min';
    /** @var string The @sum array operator. */
    const sumKeyValueOperator = 'sum';
    /** @var string The @unionOfArrays array operator. */
    const unionOfArraysKeyValueOperator = 'unionOfArrays';
    /** @var string The @unionOfObjects array operator. */
    const unionOfObjectsKeyValueOperator = 'unionOfObjects';
    /** @var string The @unionOfSets array operator. */
    const unionOfSetsKeyValueOperator = 'unionOfSets';
}