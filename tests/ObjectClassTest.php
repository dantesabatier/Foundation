<?php

declare(strict_types=1);

namespace Sabatier\Foundation\Tests;

use PHPUnit\Framework\TestCase;
use Sabatier\Foundation\ObjectClass;
use Sabatier\Foundation\UndefinedKeyException;

/**
 * Tests for src/ObjectClass.php.
 *
 * Regression guards:
 *  - hasProperty wraps the property axis (property_exists) the way responds wraps the
 *    method axis (method_exists): the two are separate namespaces in PHP and must not
 *    be conflated the way Cocoa's respondsToSelector: conflates them;
 *  - hasProperty reports declared properties regardless of visibility, and — the edge
 *    that motivated a dedicated method — reports virtual get-only property hooks
 *    (PHP 8.4+) that have no backing store;
 *  - valueForKey/setValueForKey route through hasProperty: a declared property is read
 *    and written directly, an undeclared key falls through to valueForUndefinedKey.
 */
final class ObjectClassTest extends TestCase
{
    private function fixture(): ObjectClass
    {
        return new class extends ObjectClass {
            public string $plain = "p";
            private string $secret = "s";
            public string $virtual {
                get => "v";
            }

            public function aMethod(): void
            {
            }
        };
    }

    public function testHasPropertyIsTheDeclaredPropertyAxis(): void
    {
        $object = $this->fixture();
        $this->assertTrue($object->hasProperty("plain"), "a public property is reported");
        $this->assertTrue($object->hasProperty("secret"), "a private property is reported regardless of visibility");
        $this->assertFalse($object->hasProperty("missing"), "an undeclared key is not reported");
        // A method is not a property: hasProperty and responds are separate axes.
        $this->assertFalse($object->hasProperty("aMethod"), "a method name is not a property");
        $this->assertTrue($object->responds("aMethod"), "the method axis still sees the method");
    }

    public function testHasPropertyReportsVirtualPropertyHooks(): void
    {
        // The edge that justified a dedicated method: a get-only hook has no backing
        // store, yet property_exists (and therefore hasProperty) still reports it, so
        // valueForKey reads it directly instead of falling through to undefined-key.
        $object = $this->fixture();
        $this->assertTrue($object->hasProperty("virtual"), "a virtual get-only property hook is reported");
        $this->assertFalse($object->responds("virtual"), "the hook is a property, not a method");
    }

    public function testValueForKeyRoutesThroughHasProperty(): void
    {
        $object = $this->fixture();
        $this->assertSame("p", $object->valueForKey("plain"), "a declared property is read directly");
        $this->assertSame("v", $object->valueForKey("virtual"), "a virtual property hook is read directly");
    }

    public function testValueForKeyOfAnUndeclaredKeyFallsThrough(): void
    {
        $object = $this->fixture();
        $this->expectException(UndefinedKeyException::class);
        $object->valueForKey("missing");
    }

    public function testSetValueForKeyRoutesThroughHasProperty(): void
    {
        $object = $this->fixture();
        $object->setValueForKey("changed", "plain");
        $this->assertSame("changed", $object->valueForKey("plain"), "a declared property is written directly");
    }

    public function testSetValueForKeyOfAnUndeclaredKeyFallsThrough(): void
    {
        $object = $this->fixture();
        $this->expectException(UndefinedKeyException::class);
        $object->setValueForKey("x", "missing");
    }
}
