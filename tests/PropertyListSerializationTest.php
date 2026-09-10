<?php

declare(strict_types=1);

namespace Sabatier\Foundation\Tests;

use Override;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Sabatier\Foundation\ArrayClass;
use Sabatier\Foundation\Date;
use Sabatier\Foundation\Dictionary;
use Sabatier\Foundation\KeyedArchiver;
use Sabatier\Foundation\KeyedUnarchiver;
use Sabatier\Foundation\PropertyListSerialization;
use Sabatier\Foundation\PropertyListSerializationFormat;
use Sabatier\Foundation\UUID;

/**
 * Tests for src/PropertyListSerialization.php, src/PropertyListSerializer.php and the
 * KeyedArchiver / KeyedUnarchiver pair.
 *
 * Regression guard:
 *  - the serializer built its value-carrying elements with
 *    DOMDocument::createElement($name, $value), whose second argument is parsed as markup
 *    rather than escaped. A value holding a bare ampersand produced an unterminated entity
 *    reference, createElement() returned false and the whole value was silently dropped
 *    ("a&b" came back as an empty string); a value holding "&amp;" was decoded on the way
 *    in and came back as "&". Keys had the same defect, so a dictionary key containing an
 *    ampersand corrupted the structure. Values are written through a text node now, which
 *    escapes instead of parsing. This reached anything that persists a plist — UserDefaults
 *    among them, where a stored string as ordinary as "Ventas & Marketing" was lost.
 */
final class PropertyListSerializationTest extends TestCase
{
    #[Override]
    protected function setUp(): void
    {
        // A Date round-trip renders and re-reads in the process time zone.
        date_default_timezone_set("UTC");
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function escapingProvider(): iterable
    {
        yield "bare ampersand" => ["&"];
        yield "ampersand between letters" => ["a&b"];
        yield "an escaped ampersand stays one character" => ["&amp;"];
        yield "angle brackets" => ["<tag>"];
        yield "markup-looking value" => ["<tag>&amp;</tag>"];
        yield "double quotes" => ["\"quoted\""];
        yield "single quotes" => ["'single'"];
        yield "everything at once" => ["<a href=\"x&y\">'z'</a>"];
    }

    #[DataProvider("escapingProvider")]
    public function testStringsSurviveTheXMLRoundTrip(string $value): void
    {
        $data = PropertyListSerialization::data($value, PropertyListSerializationFormat::xml);

        $this->assertSame($value, PropertyListSerialization::propertyList($data));
    }

    #[DataProvider("escapingProvider")]
    public function testDictionaryKeysSurviveTheXMLRoundTrip(string $key): void
    {
        $dictionary = new Dictionary([$key => "valor"]);

        $data = PropertyListSerialization::data($dictionary, PropertyListSerializationFormat::xml);
        $restored = PropertyListSerialization::propertyList($data);

        $this->assertInstanceOf(Dictionary::class, $restored);
        $this->assertSame("valor", $restored[$key]);
    }

    /**
     * @return iterable<string, array{mixed}>
     */
    public static function scalarProvider(): iterable
    {
        yield "string" => ["hola"];
        yield "empty string" => [""];
        yield "integer" => [42];
        yield "negative integer" => [-17];
        yield "zero" => [0];
        yield "float" => [2.5];
        yield "true" => [true];
        yield "false" => [false];
        yield "accented text" => ["José Ñandú"];
    }

    #[DataProvider("scalarProvider")]
    public function testScalarsSurviveTheXMLRoundTrip(mixed $value): void
    {
        $data = PropertyListSerialization::data($value, PropertyListSerializationFormat::xml);

        $this->assertSame($value, PropertyListSerialization::propertyList($data));
    }

    public function testCollectionsSurviveTheXMLRoundTrip(): void
    {
        $plist = new Dictionary([
            "list" => new ArrayClass([1, 2, 3]),
            "strings" => new ArrayClass(["a", "b"]),
            "nested" => new Dictionary(["inner" => new Dictionary(["deep" => "valor"])]),
            "empty list" => new ArrayClass([]),
        ]);

        $data = PropertyListSerialization::data($plist, PropertyListSerializationFormat::xml);
        $restored = PropertyListSerialization::propertyList($data);

        $this->assertInstanceOf(Dictionary::class, $restored);
        $this->assertSame(json_encode($plist->jsonSerialize()), json_encode($restored->jsonSerialize()));
    }

    public function testDateSurvivesTheXMLRoundTrip(): void
    {
        $date = Date::dateWithTimeIntervalSince1970((float)mktime(12, 30, 0, 6, 15, 2022));
        $plist = new Dictionary(["cuando" => $date]);

        $data = PropertyListSerialization::data($plist, PropertyListSerializationFormat::xml);
        $restored = PropertyListSerialization::propertyList($data);

        $this->assertInstanceOf(Date::class, $restored["cuando"]);
        $this->assertSame($date->description, $restored["cuando"]->description);
    }

    /**
     * @return iterable<string, array{PropertyListSerializationFormat}>
     */
    public static function formatProvider(): iterable
    {
        yield "xml" => [PropertyListSerializationFormat::xml];
        yield "binary" => [PropertyListSerializationFormat::binary];
        yield "openStep" => [PropertyListSerializationFormat::openStep];
    }

    #[DataProvider("formatProvider")]
    public function testEveryFormatRoundTripsADictionary(PropertyListSerializationFormat $format): void
    {
        $plist = new Dictionary(["k" => "v", "n" => 5]);

        $data = PropertyListSerialization::data($plist, $format);
        $restored = PropertyListSerialization::propertyList($data);

        $this->assertInstanceOf(Dictionary::class, $restored);
        $this->assertSame(json_encode($plist->jsonSerialize()), json_encode($restored->jsonSerialize()));
    }

    #[DataProvider("formatProvider")]
    public function testEveryFormatEscapesAnAmpersand(PropertyListSerializationFormat $format): void
    {
        $plist = new Dictionary(["área" => "Ventas & Marketing"]);

        $data = PropertyListSerialization::data($plist, $format);
        $restored = PropertyListSerialization::propertyList($data);

        $this->assertSame("Ventas & Marketing", $restored["área"]);
    }

    /**
     * @return iterable<string, array{mixed}>
     */
    public static function archiverProvider(): iterable
    {
        yield "string" => ["hola"];
        yield "string with markup" => ["a&b<c>"];
        yield "integer" => [42];
        yield "float" => [2.5];
        yield "bool" => [true];
        yield "null" => [null];
    }

    #[DataProvider("archiverProvider")]
    public function testKeyedArchiverRoundTripsScalars(mixed $value): void
    {
        $data = KeyedArchiver::archivedData($value);

        $this->assertSame($value, KeyedUnarchiver::unarchiveTopLevelObjectWithData($data));
    }

    public function testKeyedArchiverRoundTripsAnObjectGraph(): void
    {
        $graph = new Dictionary([
            "list" => new ArrayClass([1, 2]),
            "nested" => new Dictionary(["c" => 3]),
            "date" => Date::dateWithTimeIntervalSince1970(1655296200.0),
            "uuid" => new UUID(),
        ]);

        $restored = KeyedUnarchiver::unarchiveTopLevelObjectWithData(KeyedArchiver::archivedData($graph));

        $this->assertInstanceOf(Dictionary::class, $restored);
        $this->assertSame(json_encode($graph->jsonSerialize()), json_encode($restored->jsonSerialize()));
    }

    public function testArchivingAnArchiveIsIdempotent(): void
    {
        // Archiving something already archived must not double-encode it, so a value that passes through the archiver twice still unarchives to the original.
        $once = KeyedArchiver::archivedData("hola");
        $twice = KeyedArchiver::archivedData($once);

        $this->assertSame($once, $twice);
        $this->assertSame("hola", KeyedUnarchiver::unarchiveTopLevelObjectWithData($twice));
    }

    public function testUnarchivingSomethingThatIsNotAnArchiveReturnsItUnchanged(): void
    {
        $this->assertSame("texto plano", KeyedUnarchiver::unarchiveTopLevelObjectWithData("texto plano"));
    }
}
