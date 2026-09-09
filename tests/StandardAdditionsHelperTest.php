<?php

declare(strict_types=1);

namespace Sabatier\Foundation\Tests;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Sabatier\Foundation\CompareOptions;

use function Sabatier\Foundation\capitalize;
use function Sabatier\Foundation\full_user_name;
use function Sabatier\Foundation\home_directory;
use function Sabatier\Foundation\hsl_to_hex;
use function Sabatier\Foundation\is_ascii;
use function Sabatier\Foundation\is_hidden;
use function Sabatier\Foundation\is_password;
use function Sabatier\Foundation\is_serialized;
use function Sabatier\Foundation\process_name;
use function Sabatier\Foundation\slug;
use function Sabatier\Foundation\string_compare;
use function Sabatier\Foundation\user_name;

/**
 * Tests the helpers in src/StandardAdditions.php that StandardAdditionsTest leaves
 * uncovered — the colour conversion, the string transforms, the serialization probe and
 * the environment lookups.
 *
 * Regression guards:
 *  - hsl_to_hex() picks a different channel arrangement in each 60-degree sector, so
 *    every one of its six branches is exercised: an off-by-one in a boundary would
 *    silently produce the wrong colour rather than fail;
 *  - slug() folds diacritics before slugging, so an accented word survives as its ASCII
 *    equivalent instead of collapsing into separators;
 *  - string_compare() clamps its result to the ComparisonResult range, so it answers
 *    -1, 0 or 1 rather than the raw strcmp difference;
 *  - is_serialized() rejects a string too short to be serialized, and one whose
 *    terminator is missing, before it tries to unserialize anything;
 *  - is_password() reports whether a string IS a password hash, not whether it would
 *    make a good password. The name reads the other way round, which is the trap.
 *
 * What stays uncovered here is platform-conditional rather than untested: the POSIX
 * branches of the user lookups, which need posix_getpwuid(); the shell_exec attribute
 * probe in is_hidden(), gated behind the USE_UNSAFE_FUNCTIONS constant that ships
 * false; and the ICU Collator path in string_compare(), which only runs for a
 * locale-sensitive comparison.
 */
final class StandardAdditionsHelperTest extends TestCase
{
    /** @return iterable<string, array{int, string}> */
    public static function hueProvider(): iterable
    {
        yield "red sector" => [0, "#FF0000"];
        yield "yellow-green sector" => [90, "#80FF00"];
        yield "green-cyan sector" => [150, "#00FF80"];
        yield "cyan-blue sector" => [210, "#0080FF"];
        yield "blue-magenta sector" => [270, "#8000FF"];
        yield "magenta-red sector" => [330, "#FF0080"];
    }

    #[DataProvider("hueProvider")]
    public function testEachHueSectorProducesItsColour(int $hue, string $expected): void
    {
        $this->assertSame($expected, hsl_to_hex($hue, 100, 50));
    }

    public function testLightnessAndSaturationMoveTheColour(): void
    {
        $this->assertSame("#FFFFFF", hsl_to_hex(0, 100, 100), "full lightness is white whatever the hue");
        $this->assertSame("#000000", hsl_to_hex(0, 100, 0), "no lightness is black");
        $this->assertSame("#808080", hsl_to_hex(0, 0, 50), "no saturation is grey");
    }

    /** @return iterable<string, array{string, string}> */
    public static function slugProvider(): iterable
    {
        yield "plain words" => ["Hola Mundo", "hola-mundo"];
        yield "accented words" => ["Ñandú Ágil", "nandu-agil"];
        yield "underscores become separators" => ["a1_b2", "a1-b2"];
        yield "nothing alphanumeric" => ["  ---  ", ""];
        yield "already a slug" => ["hola-mundo", "hola-mundo"];
    }

    #[DataProvider("slugProvider")]
    public function testSlugReducesAStringToItsAsciiWords(string $input, string $expected): void
    {
        $this->assertSame($expected, slug($input));
    }

    public function testSlugAcceptsAnotherSeparator(): void
    {
        $this->assertSame("hola_mundo", slug("Hola Mundo", "_"));
    }

    public function testCapitalizeTitleCasesEveryWord(): void
    {
        $this->assertSame("Hola Mundo Cruel", capitalize("hola mundo cruel"));
        $this->assertSame("Ñandú Ágil", capitalize("ñandú ágil"), "the transform is UTF-8 aware");
    }

    /** @return iterable<string, array{string, string, int, int}> */
    public static function comparisonProvider(): iterable
    {
        yield "ascending" => ["a", "b", CompareOptions::none, -1];
        yield "descending" => ["b", "a", CompareOptions::none, 1];
        yield "same" => ["a", "a", CompareOptions::none, 0];
        yield "case folded" => ["A", "a", CompareOptions::caseInsensitive, 0];
        yield "case sensitive" => ["A", "a", CompareOptions::none, -1];
    }

    #[DataProvider("comparisonProvider")]
    public function testComparisonIsClampedToTheOrderingRange(string $string, string $other, int $options, int $expected): void
    {
        $this->assertSame($expected, string_compare($string, $other, $options), "the raw difference is clamped to -1, 0 or 1");
    }

    public function testALongDifferenceStillClampsToOne(): void
    {
        $this->assertSame(1, string_compare("zzzz", "aaaa"), "strcmp would answer a larger number here");
        $this->assertSame(-1, string_compare("aaaa", "zzzz"));
    }

    /** @return iterable<string, array{string, bool}> */
    public static function serializedProvider(): iterable
    {
        yield "serialized array" => ["a:1:{i:0;i:1;}", true];
        yield "serialized string" => ["s:1:\"x\";", true];
        yield "serialized boolean" => ["b:0;", true];
        yield "serialized integer" => ["i:1;", true];
        yield "plain text" => ["no", false];
        yield "empty" => ["", false];
        yield "too short" => ["a:1", false];
        yield "missing terminator" => ["a:1:{i:0;i:1", false];
    }

    #[DataProvider("serializedProvider")]
    public function testSerializedStringsAreRecognised(string $value, bool $expected): void
    {
        $this->assertSame($expected, is_serialized($value));
    }

    public function testARoundTrippedValueIsRecognised(): void
    {
        $this->assertTrue(is_serialized(serialize(["a" => 1, "b" => [2, 3]])));
        $this->assertTrue(is_serialized(serialize(null)));
    }

    /** @return iterable<string, array{string, bool}> */
    public static function hiddenProvider(): iterable
    {
        yield "dot file" => [".git", true];
        yield "dot file in a path" => ["/tmp/.hidden", true];
        yield "ordinary file" => ["visible.txt", false];
        yield "ordinary file in a path" => ["/tmp/visible.txt", false];
    }

    #[DataProvider("hiddenProvider")]
    public function testADotPrefixMarksAFileHidden(string $filename, bool $expected): void
    {
        $this->assertSame($expected, is_hidden($filename));
    }

    public function testAsciiDetectionRejectsWideCharacters(): void
    {
        $this->assertTrue(is_ascii("abc123"));
        $this->assertFalse(is_ascii("ñ"));
        $this->assertTrue(is_ascii(""), "an empty string holds no wide characters");
    }

    public function testPasswordDetectionRecognisesAHashRatherThanAPassword(): void
    {
        $this->assertTrue(is_password(password_hash("secret", PASSWORD_DEFAULT)), "a real hash is recognised");
        $this->assertFalse(is_password("P@ssw0rd!x"), "a strong plaintext password is not a hash");
        $this->assertFalse(is_password("abc"));
    }

    public function testTheEnvironmentLookupsAnswerSomething(): void
    {
        // The values are machine-specific, so the contract worth pinning is that each lookup resolves rather than answering an empty string.
        $this->assertNotSame("", home_directory());
        $this->assertNotSame("", user_name());
        $this->assertNotSame("", process_name());
        $this->assertIsString(full_user_name());
    }
}
