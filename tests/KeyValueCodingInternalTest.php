<?php

declare(strict_types=1);

namespace Sabatier\Foundation\Tests;

use PHPUnit\Framework\TestCase;
use Sabatier\Foundation\KeyValueOperator;

use function Sabatier\Foundation\components_from_key_path;
use function Sabatier\Foundation\kvc_components;
use function Sabatier\Foundation\kvc_operator_from_key;

/** Exercises the internal parsing helpers used by key-value coding. */
final class KeyValueCodingInternalTest extends TestCase
{
    public function testKeyPathIsSplitAtTheFirstSeparator(): void
    {
        $components = components_from_key_path("department.manager.name");
        $this->assertSame("department", $components->key);
        $this->assertSame("manager.name", $components->remainderPath);

        $components = components_from_key_path("name");
        $this->assertSame("name", $components->key);
        $this->assertNull($components->remainderPath);
    }

    public function testKeyPathPreservesEmptyAndRepeatedSegments(): void
    {
        $components = components_from_key_path("");
        $this->assertSame("", $components->key);
        $this->assertNull($components->remainderPath);

        $components = components_from_key_path(".name");
        $this->assertSame("", $components->key);
        $this->assertSame("name", $components->remainderPath);

        $components = components_from_key_path("name.");
        $this->assertSame("name", $components->key);
        $this->assertNull($components->remainderPath);

        $components = components_from_key_path("name..given");
        $this->assertSame("name", $components->key);
        $this->assertSame(".given", $components->remainderPath);
    }

    public function testEveryDeclaredCollectionOperatorIsRecognized(): void
    {
        foreach ([
            KeyValueOperator::countKeyValueOperator,
            KeyValueOperator::averageKeyValueOperator,
            KeyValueOperator::medianKeyValueOperator,
            KeyValueOperator::modeKeyValueOperator,
            KeyValueOperator::standardDeviationKeyValueOperator,
            KeyValueOperator::distinctUnionOfArraysKeyValueOperator,
            KeyValueOperator::distinctUnionOfObjectsKeyValueOperator,
            KeyValueOperator::distinctUnionOfSetsKeyValueOperator,
            KeyValueOperator::maximumKeyValueOperator,
            KeyValueOperator::minimumKeyValueOperator,
            KeyValueOperator::sumKeyValueOperator,
            KeyValueOperator::unionOfArraysKeyValueOperator,
            KeyValueOperator::unionOfObjectsKeyValueOperator,
            KeyValueOperator::unionOfSetsKeyValueOperator,
        ] as $operator) {
            $this->assertSame($operator, kvc_operator_from_key("@$operator"));
        }
    }

    public function testInvalidCollectionOperatorsAreRejected(): void
    {
        foreach (["", "@", "sum", "@Sum", "@unknown", "@@sum"] as $key) {
            $this->assertNull(kvc_operator_from_key($key), $key);
        }
    }

    public function testLegacyComponentsSeparateCollectionOperatorAndPropertyPath(): void
    {
        $this->assertSame(["employees", "sum", "salary"], kvc_components("employees.salary@sum"));
        $this->assertSame(["employees", "count", ""], kvc_components("employees@count"));
        $this->assertSame(["", "count", ""], kvc_components("@count"));
        $this->assertSame(["employees", "", "salary"], kvc_components("employees.salary"));
    }

    public function testLegacyComponentsTrimAndDiscardEmptyPathSegments(): void
    {
        $this->assertSame(["employees", "sum", "salary.amount"], kvc_components(" . employees .. salary . amount . @sum"));
        $this->assertSame(["", "", ""], kvc_components(""));
    }
}
