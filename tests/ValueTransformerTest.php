<?php

declare(strict_types=1);

namespace Sabatier\Foundation\Tests;

use Exception;
use Override;
use PHPUnit\Framework\TestCase;
use ReflectionProperty;
use Sabatier\Foundation\InternalInconsistencyException;
use Sabatier\Foundation\NegateBooleanTransformer;
use Sabatier\Foundation\Number;
use Sabatier\Foundation\UnarchiveFromDataTransformer;
use Sabatier\Foundation\ValueTransformer;

use const Sabatier\Foundation\NegateBooleanTransformerName;
use const Sabatier\Foundation\SecureUnarchiveFromDataTransformerName;
use const Sabatier\Foundation\UnarchiveFromDataTransformerName;

final class ValueTransformerTest extends TestCase
{
    #[Override]
    protected function tearDown(): void
    {
        // The registry is process-wide, so each test must let ValueTransformer rebuild its initial state.
        new ReflectionProperty(ValueTransformer::class, "valueTransformers")->setValue(null, null);
    }

    public function testRegistryExposesTheBuiltInTransformers(): void
    {
        $names = ValueTransformer::valueTransformerNames();

        $this->assertContains(NegateBooleanTransformerName, $names->array);
        $this->assertContains(UnarchiveFromDataTransformerName, $names->array);
        $this->assertContains(SecureUnarchiveFromDataTransformerName, $names->array);
    }

    public function testLookupReturnsTheSharedInstanceForABuiltInName(): void
    {
        $transformer = ValueTransformer::valueTransformerForName(NegateBooleanTransformerName);

        $this->assertInstanceOf(NegateBooleanTransformer::class, $transformer);
        $this->assertSame($transformer, ValueTransformer::valueTransformerForName(NegateBooleanTransformerName));
        $this->assertSame("<shared NegateBoolean transformer>", $transformer->description);
    }

    public function testSecureAndPlainUnarchiveAreSeparateInstances(): void
    {
        $plain = ValueTransformer::valueTransformerForName(UnarchiveFromDataTransformerName);
        $secure = ValueTransformer::valueTransformerForName(SecureUnarchiveFromDataTransformerName);

        $this->assertInstanceOf(UnarchiveFromDataTransformer::class, $plain);
        $this->assertInstanceOf(UnarchiveFromDataTransformer::class, $secure);
        $this->assertNotSame($plain, $secure);
    }

    public function testLookupReturnsNullForAnUnknownName(): void
    {
        $this->assertNull(ValueTransformer::valueTransformerForName("NoSuchTransformer"));
    }

    public function testLookupRejectsAClassThatIsNotAValueTransformer(): void
    {
        $this->assertNull(ValueTransformer::valueTransformerForName("stdClass"));
    }

    public function testTransformedValueClassRequiresAConcreteImplementation(): void
    {
        $this->expectException(InternalInconsistencyException::class);
        $this->expectExceptionMessage("transformedValueClass requires a subclass implementation");

        ValueTransformer::transformedValueClass();
    }

    public function testDefaultTransformIsAPassthroughAndReverseReappliesIt(): void
    {
        $transformer = new ValueTransformerTestPassthrough();

        $this->assertTrue(ValueTransformerTestPassthrough::allowsReverseTransformation());
        $this->assertSame("x", $transformer->transformedValue("x"));
        $this->assertSame("x", $transformer->reverseTransformedValue("x"));
    }

    public function testReverseTransformIsFatalWhenNotAllowed(): void
    {
        $transformer = new ValueTransformerTestIrreversible();

        $this->assertFalse(ValueTransformerTestIrreversible::allowsReverseTransformation());
        $this->assertSame("x", $transformer->transformedValue("x"));

        $this->expectException(InternalInconsistencyException::class);

        $transformer->reverseTransformedValue("x");
    }

    public function testNegateBooleanInvertsANumber(): void
    {
        $transformer = new NegateBooleanTransformer();

        $this->assertSame(Number::class, NegateBooleanTransformer::transformedValueClass());
        $this->assertFalse($transformer->transformedValue(new Number(true))->boolValue);
        $this->assertTrue($transformer->transformedValue(new Number(false))->boolValue);
        $this->assertTrue($transformer->reverseTransformedValue($transformer->transformedValue(new Number(true)))->boolValue);
    }

    /** @throws Exception */
    public function testUnarchiveFromDataRoundTripsAValue(): void
    {
        $transformer = new UnarchiveFromDataTransformer();

        $archived = $transformer->transformedValue(["key" => 1]);

        $this->assertIsString($archived);
        $this->assertNotSame("", $archived);
        $this->assertSame(["key" => 1], $transformer->reverseTransformedValue($archived));
        $this->assertSame("<shared UnarchiveFromData transformer>", $transformer->description);
    }

    /** @throws Exception */
    public function testUnarchiveFromDataPassesNullAndNonStringsThrough(): void
    {
        $transformer = new UnarchiveFromDataTransformer();

        $this->assertNull($transformer->transformedValue(null));
        $this->assertSame(5, $transformer->reverseTransformedValue(5));
    }

    public function testLookupAutoRegistersATransformerNamedByItsClass(): void
    {
        $transformer = ValueTransformer::valueTransformerForName(ValueTransformerTestPassthrough::class);

        $this->assertInstanceOf(ValueTransformerTestPassthrough::class, $transformer);
        $this->assertSame($transformer, ValueTransformer::valueTransformerForName(ValueTransformerTestPassthrough::class));
        $this->assertContains(ValueTransformerTestPassthrough::class, ValueTransformer::valueTransformerNames()->array);
    }

    public function testAnExplicitlyRegisteredTransformerWinsForItsName(): void
    {
        $transformer = new ValueTransformerTestIrreversible();

        ValueTransformer::setValueTransformerForName($transformer, "custom");

        $this->assertSame($transformer, ValueTransformer::valueTransformerForName("custom"));
        $this->assertContains("custom", ValueTransformer::valueTransformerNames()->array);
    }
}

final class ValueTransformerTestPassthrough extends ValueTransformer
{
}

final class ValueTransformerTestIrreversible extends ValueTransformer
{
    #[Override]
    public static function allowsReverseTransformation(): bool
    {
        return false;
    }
}
