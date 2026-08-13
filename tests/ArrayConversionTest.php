<?php

declare(strict_types=1);

namespace Sabatier\Foundation\Tests;

use PHPUnit\Framework\TestCase;
use Sabatier\Foundation\ArrayClass;
use Sabatier\Foundation\Dictionary;
use Sabatier\Foundation\Nil;
use stdClass;
use TypeError;

/**
 * Tests for src/ArrayConverter.php and the two conversion strategies behind it.
 *
 * Regression guard: the conversion picked between `ArrayClass` and `Dictionary` with
 * `is_sequential()` alone, which reports an empty array as sequential. A JSON `{}` decoded
 * associatively is that same empty array, so every empty object became an empty `ArrayClass`
 * and the distinction JSON draws between `{}` and `[]` was lost — a caller that declared a
 * `Dictionary` got an `ArrayClass` and a `TypeError` with it. Passing the `stdClass` that
 * `json_decode` returns without the associative flag now keeps an empty object a `Dictionary`.
 *
 * Only `stdClass` is taken apart. Any other object — including a Foundation collection already
 * built by the caller — is a value in its own right: decomposing one into its properties would
 * expose the hooks of its class instead of its elements.
 */
final class ArrayConversionTest extends TestCase
{
    public function testEmptyObjectBecomesADictionaryAndEmptyListAnArrayClass(): void
    {
        $decoded = Dictionary::dictionaryWithArray(json_decode('{"object":{},"list":[]}'));
        $this->assertInstanceOf(Dictionary::class, $decoded["object"], "an empty {} stays a Dictionary");
        $this->assertInstanceOf(ArrayClass::class, $decoded["list"], "an empty [] stays an ArrayClass");
        $this->assertSame(0, $decoded["object"]->count, "the empty object carries no entries");
        $this->assertSame(0, $decoded["list"]->count, "the empty list carries no elements");
    }

    public function testPopulatedObjectsAndListsKeepTheirOwnType(): void
    {
        $decoded = Dictionary::dictionaryWithArray(json_decode('{"object":{"a":1},"list":[1,2]}'));
        $this->assertInstanceOf(Dictionary::class, $decoded["object"], "a populated object is a Dictionary");
        $this->assertInstanceOf(ArrayClass::class, $decoded["list"], "a populated list is an ArrayClass");
        $this->assertSame(1, $decoded["object"]["a"], "the object keeps its entry");
        $this->assertSame(2, $decoded["list"][1], "the list keeps its elements in order");
    }

    public function testTheDistinctionSurvivesArbitraryNesting(): void
    {
        $decoded = Dictionary::dictionaryWithArray(json_decode('{"outer":{"inner":{},"deep":[{"k":{}}]}}'));
        $this->assertInstanceOf(Dictionary::class, $decoded["outer"]["inner"], "an empty object nested in an object");
        $this->assertInstanceOf(ArrayClass::class, $decoded["outer"]["deep"], "a list nested in an object");
        $this->assertInstanceOf(Dictionary::class, $decoded["outer"]["deep"][0]["k"], "an empty object nested inside a list");
    }

    public function testAnEmptyObjectDecodedAssociativelyIsIndistinguishableFromAList(): void
    {
        $associative = Dictionary::dictionaryWithArray(json_decode('{"object":{},"list":[]}', true));
        $this->assertInstanceOf(ArrayClass::class, $associative["object"], "the associative flag erases the {} before Foundation sees it");
        $this->assertInstanceOf(ArrayClass::class, $associative["list"], "which is the same array the [] decodes to");
    }

    public function testABareArrayConvertsAsItAlwaysHas(): void
    {
        $this->assertInstanceOf(ArrayClass::class, ArrayClass::arrayWithArray([1, 2, 3]), "a list is an ArrayClass");
        $this->assertInstanceOf(Dictionary::class, Dictionary::dictionaryWithArray(["a" => 1]), "an associative array is a Dictionary");
        $this->assertInstanceOf(Dictionary::class, Dictionary::dictionaryWithArray([]), "an empty array requested as a Dictionary is one");
        $this->assertInstanceOf(Dictionary::class, Dictionary::dictionaryWithArray(["a" => ["b" => 1]])["a"], "a nested associative array is a Dictionary");
        $this->assertInstanceOf(ArrayClass::class, Dictionary::dictionaryWithArray(["a" => [1, 2]])["a"], "a nested list is an ArrayClass");
    }

    public function testAnAlreadyBuiltCollectionIsStoredAsItIs(): void
    {
        $elements = new ArrayClass([1, 2]);
        $converted = Dictionary::dictionaryWithArray(["tags" => $elements]);
        $this->assertInstanceOf(ArrayClass::class, $converted["tags"], "the collection is not taken apart");
        $this->assertSame(2, $converted["tags"]->count, "and keeps its elements");
    }

    public function testAnObjectOtherThanStdClassIsRejected(): void
    {
        $this->expectException(TypeError::class);
        Dictionary::dictionaryWithArray(new class {
            public int $visible = 1;
        });
    }

    public function testNullHandlingIsUnchangedForObjects(): void
    {
        $preserved = Dictionary::dictionaryWithArray(json_decode('{"a":null}'));
        $this->assertInstanceOf(Nil::class, $preserved["a"], "a null is preserved as Nil by default");
        $dropped = Dictionary::dictionaryWithArray(json_decode('{"a":null}'), false);
        $this->assertNull($dropped["a"], "and is dropped when preserveNull is false");
    }

    public function testStdClassKeysAreNotMangled(): void
    {
        $object = new stdClass();
        $object->name = "Ana";
        $object->nested = new stdClass();
        $converted = Dictionary::dictionaryWithArray($object);
        $this->assertSame("Ana", $converted["name"], "a property becomes an entry under its own name");
        $this->assertInstanceOf(Dictionary::class, $converted["nested"], "a nested empty stdClass is a Dictionary");
    }
}
