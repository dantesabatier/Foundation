<?php

declare(strict_types=1);

namespace Sabatier\Foundation\Tests;

use Countable;
use InvalidArgumentException;
use JsonSerializable;
use Override;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Sabatier\Foundation\ArrayClass;
use Sabatier\Foundation\Comparable;
use Sabatier\Foundation\Dictionary;
use Sabatier\Foundation\InternalInconsistencyException;
use Sabatier\Foundation\Invocation;
use Sabatier\Foundation\KeyValueObservedChange;
use Sabatier\Foundation\KeyValueObservingOptions;
use Sabatier\Foundation\ObjectClass;
use Sabatier\Foundation\Set;
use Sabatier\Foundation\UndefinedKeyException;
use stdClass;

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

            public function aMethod(): string
            {
                return $this->secret;
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

    public function testTypeIntrospectionAnswersAcrossTheHierarchy(): void
    {
        $child = new ObjectClassTestChild();

        $this->assertTrue($child->isKind(ObjectClassTestParent::class), "a subclass is a kind of its superclass");
        $this->assertTrue($child->isKind(ObjectClass::class), "the root class is reached transitively");
        $this->assertFalse($child->isKind(ObjectClassTestUnrelated::class));
        $this->assertTrue($child->isMember(ObjectClassTestChild::class), "the exact class is reported");
        $this->assertFalse($child->isMember(ObjectClassTestParent::class), "a superclass is not the exact class");
        $this->assertFalse($child->isMember(ObjectClass::class));
        $this->assertFalse($child->isMember(ObjectClassTestUnrelated::class));
        $this->assertTrue($child->isSubclass(ObjectClassTestParent::class), "a strict superclass is reported");
        $this->assertFalse($child->isSubclass(ObjectClassTestChild::class), "a class is not a subclass of itself");
    }

    public function testConformsReportsImplementedProtocols(): void
    {
        $object = $this->fixture();

        $this->assertTrue($object->conforms(Comparable::class));
        $this->assertTrue($object->conforms(JsonSerializable::class));
        $this->assertFalse($object->conforms(Countable::class));
    }

    public function testClassIdentityPropertiesDescribeTheInstance(): void
    {
        $child = new ObjectClassTestChild();

        $this->assertSame(ObjectClassTestChild::class, $child->class);
        $this->assertSame(ObjectClassTestParent::class, $child->superclass);
        $this->assertSame(ObjectClassTestChild::class, $child->canonicalDescription);
        $this->assertSame(sprintf("<%s %d>", ObjectClassTestChild::class, $child->hash), $child->description);
        $this->assertSame($child->description, $child->debugDescription);
        $this->assertSame($child->description, (string)$child);
    }

    public function testHashIsTheInstanceIdentity(): void
    {
        $one = new ObjectClassTestChild();
        $another = new ObjectClassTestChild();

        $this->assertSame(spl_object_id($one), $one->hash);
        $this->assertNotSame($one->hash, $another->hash, "two instances never share an identity hash");
    }

    public function testEqualityIsIdentityByDefault(): void
    {
        $one = new ObjectClassTestChild();
        $another = new ObjectClassTestChild();

        $this->assertTrue($one->isEqual($one));
        $this->assertFalse($one->isEqual($another), "distinct instances are not equal without a conceptual override");
        $this->assertFalse($one->isEqual(5), "a non-object is never equal");
        $this->assertFalse($one->isEqual(null));
    }

    public function testInstancesRespondIsTheStaticMethodAxis(): void
    {
        $this->assertTrue(ObjectClassTestChild::instancesRespond("isKind"));
        $this->assertFalse(ObjectClassTestChild::instancesRespond("noSuchSelector"));
    }

    public function testPerformInvokesTheSelectorWithItsArguments(): void
    {
        $child = new ObjectClassTestChild();

        $this->assertTrue($child->perform("isKind", [ObjectClassTestParent::class]));
        $this->assertSame("ada+x", $child->perform("greet", ["x"]));
    }

    public function testPerformOfAnUnknownSelectorIsRejected(): void
    {
        $child = new ObjectClassTestChild();

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage("unrecognized selector sent to instance");

        $child->perform("noSuchSelector");
    }

    public function testForwardInvocationRejectsAnUnhandledSelector(): void
    {
        $child = new ObjectClassTestChild();
        $invocation = new Invocation();
        $invocation->selector = "noSuchSelector";

        $this->expectException(InvalidArgumentException::class);

        $child->forwardInvocation($invocation);
    }

    /** @return iterable<string, array{string, list<mixed>}> */
    public static function abstractMemberProvider(): iterable
    {
        yield "compare" => ["compare", [1]];
        yield "jsonSerialize" => ["jsonSerialize", []];
        yield "mutableArrayValueForKey" => ["mutableArrayValueForKey", ["k"]];
        yield "mutableSetValueForKey" => ["mutableSetValueForKey", ["k"]];
    }

    /** @param list<mixed> $arguments */
    #[DataProvider("abstractMemberProvider")]
    public function testAbstractMembersDemandAConcreteImplementation(string $selector, array $arguments): void
    {
        $child = new ObjectClassTestChild();

        $this->expectException(InternalInconsistencyException::class);
        $this->expectExceptionMessage("requires a subclass implementation");

        $child->$selector(...$arguments);
    }

    public function testSetNilValueForKeyIsFatal(): void
    {
        $child = new ObjectClassTestChild();

        $this->expectException(InternalInconsistencyException::class);
        $this->expectExceptionMessage("cannot be null");

        $child->setNilValueForKey("name");
    }

    public function testKeyPathTraversalReadsAndWritesThroughIntermediates(): void
    {
        $root = new ObjectClassTestNode("root");
        $root->child = $leaf = new ObjectClassTestNode("leaf");

        $this->assertSame("leaf", $root->valueForKeyPath("child.name"));
        $this->assertSame("root", $root->valueForKeyPath("name"), "a single segment reads like a plain key");

        $root->setValueForKeyPath("renamed", "child.name");

        $this->assertSame("renamed", $leaf->name, "the write lands on the intermediate object");
    }

    public function testKeyPathTraversalStopsAtANullIntermediate(): void
    {
        $root = new ObjectClassTestNode("root");

        $this->assertNull($root->valueForKeyPath("child.name"));

        $root->setValueForKeyPath("x", "child.name");

        $this->assertNull($root->child, "writing through a null intermediate is a no-op");
        $value = "x";
        $this->assertFalse($root->validateValueForKeyPath($value, "child.name"));
    }

    public function testKeyPathTraversalRejectsANonCompliantIntermediate(): void
    {
        $root = new ObjectClassTestNode("root");
        $root->child = new stdClass();

        $this->expectException(UndefinedKeyException::class);
        $this->expectExceptionMessage("is not key value coding compliant");

        $root->valueForKeyPath("child.name");
    }

    public function testValidationDelegatesDownTheKeyPath(): void
    {
        $root = new ObjectClassTestNode("root");
        $root->child = new ObjectClassTestNode("leaf");
        $value = "ok";

        $this->assertTrue($root->validateValueForKeyPath($value, "child.name"));
        $this->assertTrue($root->validateValueForKeyPath($value, "name"), "a single segment validates as a plain key");
    }

    public function testBulkAccessorsReadAndWriteManyKeys(): void
    {
        $node = new ObjectClassTestNode("root");

        $node->setValuesForKeys(new Dictionary(["name" => "bulk"]));

        $this->assertSame("bulk", $node->name);
        $this->assertSame(["name" => "bulk"], $node->dictionaryWithValues(new ArrayClass(["name"]))->array);
    }

    public function testAssociatedValuesAreStoredPerInstanceAndClearedByNull(): void
    {
        $one = new ObjectClassTestChild();
        $another = new ObjectClassTestChild();

        $this->assertNull($one->associatedValueForKey("k"), "an unset key reads as null");

        $one->setAssociatedValueForKey("v", "k");

        $this->assertSame("v", $one->associatedValueForKey("k"));
        $this->assertNull($another->associatedValueForKey("k"), "the store is per instance");

        $one->setAssociatedValueForKey(null, "k");

        $this->assertNull($one->associatedValueForKey("k"));
        $this->assertArrayNotHasKey("k", $one->associatedValues, "clearing removes the key rather than nulling it");

        $one->setAssociatedValueForKey(null, "neverSet");

        $this->assertArrayNotHasKey("neverSet", $one->associatedValues, "clearing an absent key is a no-op");
    }

    public function testStaticAssociatedValuesAreKeyedPerClass(): void
    {
        ObjectClassTestParent::setStaticAssociatedValueForKey("parent", "k");
        ObjectClassTestChild::setStaticAssociatedValueForKey("child", "k");

        try {
            $this->assertSame("parent", ObjectClassTestParent::staticAssociatedValueForKey("k"));
            $this->assertSame("child", ObjectClassTestChild::staticAssociatedValueForKey("k"), "a subclass keeps its own slot");
            $this->assertNull(ObjectClassTestParent::staticAssociatedValueForKey("absent"));

            ObjectClassTestParent::setStaticAssociatedValueForKey(null, "k");

            $this->assertNull(ObjectClassTestParent::staticAssociatedValueForKey("k"));
            $this->assertSame("child", ObjectClassTestChild::staticAssociatedValueForKey("k"), "clearing one class leaves the other intact");
        } finally {
            ObjectClassTestParent::setStaticAssociatedValueForKey(null, "k");
            ObjectClassTestChild::setStaticAssociatedValueForKey(null, "k");
        }
    }

    public function testDependentKeyAndAutoNotifyHooksDispatchByTheDocumentedName(): void
    {
        $this->assertSame(["first", "last"], ObjectClassTestDependent::keyPathsForValuesAffectingValueForKey("full")->array);
        $this->assertSame([], ObjectClassTestDependent::keyPathsForValuesAffectingValueForKey("unhooked")->array, "an unhooked key yields an empty set");

        $this->assertFalse(ObjectClassTestDependent::automaticallyNotifiesObserversForKey("optedOut"), "the hook opts the key out");
        $this->assertTrue(ObjectClassTestDependent::automaticallyNotifiesObserversForKey("unhooked"), "an unhooked key notifies automatically");
    }

    public function testChangingAnIngredientNotifiesTheDerivedKey(): void
    {
        $person = new ObjectClassTestDependent();
        $seen = [];
        $person->observe("full", KeyValueObservingOptions::new | KeyValueObservingOptions::old,
            function (mixed $object, KeyValueObservedChange $change) use (&$seen): void {
                $seen[] = [$change->oldValue, $change->newValue];
            });

        $person->setValueForKey("Grace", "first");

        $this->assertSame([["Ada Lovelace", "Grace Lovelace"]], $seen, "the derived key reports both sides of the change");
    }

    public function testEachIngredientOfADerivedKeyNotifiesIt(): void
    {
        $person = new ObjectClassTestDependent();
        $seen = [];
        $person->observe("full", KeyValueObservingOptions::new,
            function (mixed $object, KeyValueObservedChange $change) use (&$seen): void {
                $seen[] = $change->newValue;
            });

        $person->setValueForKey("Hopper", "last");

        $this->assertSame(["Ada Hopper"], $seen);
    }

    public function testAnUnrelatedKeyDoesNotNotifyTheDerivedKey(): void
    {
        $person = new ObjectClassTestDependent();
        $seen = [];
        $person->observe("first", KeyValueObservingOptions::new,
            function (mixed $object, KeyValueObservedChange $change) use (&$seen): void {
                $seen[] = $change->newValue;
            });

        $person->setValueForKey("Hopper", "last");

        $this->assertSame([], $seen, "only the keys that declare the dependency are announced");
    }

    public function testADependencyCycleSettlesInsteadOfRecursing(): void
    {
        $cyclic = new ObjectClassTestCyclic();
        $count = 0;
        $handler = function () use (&$count): void {
            $count++;
        };
        $cyclic->observe("a", KeyValueObservingOptions::new, $handler);
        $cyclic->observe("b", KeyValueObservingOptions::new, $handler);

        $cyclic->setValueForKey("z", "a");

        $this->assertSame(2, $count, "the changed key and its dependent are each announced exactly once");
    }

    public function testObserveDeliversChangesToTheHandler(): void
    {
        $node = new ObjectClassTestNode("ada");
        $seen = [];

        $node->observe("name", KeyValueObservingOptions::new | KeyValueObservingOptions::old,
            function (mixed $object, KeyValueObservedChange $change) use (&$seen): void {
                $seen[] = [$change->oldValue, $change->newValue];
            });

        $node->setValueForKey("grace", "name");

        $this->assertSame([["ada", "grace"]], $seen);
    }

    public function testAddObserverDeliversToObserveValueUntilRemoved(): void
    {
        $node = new ObjectClassTestNode("ada");
        $watcher = new ObjectClassTestWatcher();

        $node->addObserver($watcher, "name", KeyValueObservingOptions::new | KeyValueObservingOptions::old, "ctx");
        $node->setValueForKey("grace", "name");

        $this->assertSame([["name", "ada", "grace", "ctx"]], $watcher->events);

        $node->removeObserver($watcher, "name", "ctx");
        $node->setValueForKey("hopper", "name");

        $this->assertCount(1, $watcher->events, "no further notifications arrive after removal");
    }

    public function testInitialOptionDeliversTheCurrentValueOnRegistration(): void
    {
        $node = new ObjectClassTestNode("ada");
        $watcher = new ObjectClassTestWatcher();

        $node->addObserver($watcher, "name", KeyValueObservingOptions::initial | KeyValueObservingOptions::new);

        $this->assertSame([["name", null, "ada", null]], $watcher->events);
    }

    public function testBaseObserveValueIgnoresTheNotification(): void
    {
        $node = new ObjectClassTestNode("ada");

        $node->observeValue("name", $node, new KeyValueObservedChange());

        $this->expectNotToPerformAssertions();
    }
}

class ObjectClassTestParent extends ObjectClass
{
    public string $name = "ada";

    public function greet(string $suffix): string
    {
        return $this->name . "+" . $suffix;
    }
}

final class ObjectClassTestChild extends ObjectClassTestParent
{
}

final class ObjectClassTestUnrelated extends ObjectClass
{
}

final class ObjectClassTestNode extends ObjectClass
{
    public mixed $child = null;

    /** @param string $name The value the "name" key starts out holding. */
    public function __construct(public string $name = "")
    {
    }
}

final class ObjectClassTestDependent extends ObjectClass
{
    public string $first = "Ada";
    public string $last = "Lovelace";
    public string $full {
        get => $this->first . " " . $this->last;
    }

    public static function keyPathsForValuesAffectingFull(): Set
    {
        return new Set(["first", "last"]);
    }

    public static function automaticallyNotifiesObserversOfOptedOut(): bool
    {
        return false;
    }
}

/** Declares a dependency cycle — a malformed model that must still settle instead of recursing. */
final class ObjectClassTestCyclic extends ObjectClass
{
    public string $a = "a";
    public string $b = "b";

    public static function keyPathsForValuesAffectingA(): Set
    {
        return new Set(["b"]);
    }

    public static function keyPathsForValuesAffectingB(): Set
    {
        return new Set(["a"]);
    }
}

final class ObjectClassTestWatcher extends ObjectClass
{
    /** @var list<array{string, mixed, mixed, mixed}> */
    public array $events = [];

    #[Override]
    public function observeValue(string $keyPath, mixed $object, KeyValueObservedChange $change, mixed $context = null): void
    {
        $this->events[] = [$keyPath, $change->oldValue, $change->newValue, $context];
    }
}
