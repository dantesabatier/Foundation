<?php

declare(strict_types=1);

namespace Sabatier\Foundation\Tests;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Sabatier\Foundation\ArrayClass;
use Sabatier\Foundation\CustomStringConvertible;
use Sabatier\Foundation\Number;
use Sabatier\Foundation\ObjectClass;
use Sabatier\Foundation\Sensitive;
use Sabatier\Foundation\SensitiveValue;

final class SensitiveValueTest extends TestCase
{
    /** @return iterable<string, array{mixed, string}> */
    public static function wrappedValueProvider(): iterable
    {
        yield "string" => ["hunter2", "string(SensitiveValue)"];
        yield "integer" => [42, "int(SensitiveValue)"];
        yield "float" => [1.5, "float(SensitiveValue)"];
        yield "boolean" => [true, "bool(SensitiveValue)"];
        yield "null" => [null, "null(SensitiveValue)"];
        yield "array" => [["a", "b"], "array(SensitiveValue)"];
        yield "object" => [new Number(7), "Sabatier\\Foundation\\Number(SensitiveValue)"];
    }

    #[DataProvider("wrappedValueProvider")]
    public function testDescriptionReportsTheTypeWithoutRevealingTheValue(mixed $value, string $expected): void
    {
        $sensitive = new SensitiveValue($value);

        $this->assertSame($expected, $sensitive->description);
        $this->assertSame($expected, (string)$sensitive);
        $this->assertSame($expected, $sensitive->jsonSerialize());
        $this->assertSame(sprintf("\"%s\"", addcslashes($expected, "\\")), json_encode($sensitive));
    }

    public function testSecretIsAbsentFromEveryStringRepresentation(): void
    {
        $sensitive = new SensitiveValue("correct horse battery staple");

        $this->assertStringNotContainsString("correct horse battery staple", $sensitive->description);
        $this->assertStringNotContainsString("correct horse battery staple", (string)$sensitive);
        $this->assertStringNotContainsString("correct horse battery staple", (string)json_encode($sensitive));
    }

    public function testSensitiveValueIsAStringConvertible(): void
    {
        $this->assertInstanceOf(CustomStringConvertible::class, new SensitiveValue("secret"));
    }

    public function testDictionaryWithValuesWrapsOnlySensitiveProperties(): void
    {
        $account = new SensitiveValueTestAccount();

        $values = $account->dictionaryWithValues(new ArrayClass(["username", "password"]));

        $this->assertSame("ada", $values["username"]);
        $this->assertInstanceOf(SensitiveValue::class, $values["password"]);
        $this->assertSame("string(SensitiveValue)", (string)$values["password"]);
    }

    public function testValidationRejectsWritingARedactedValueBack(): void
    {
        $account = new SensitiveValueTestAccount();
        $redacted = $account->dictionaryWithValues(new ArrayClass(["password"]))["password"];

        $this->assertFalse($account->validateValueForKey($redacted, "password"));
        $this->assertSame("s3cr3t", $account->password);
    }
}

final class SensitiveValueTestAccount extends ObjectClass
{
    public string $username = "ada";

    #[Sensitive]
    public string $password = "s3cr3t";
}
