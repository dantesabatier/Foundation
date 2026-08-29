<?php

declare(strict_types=1);

namespace Sabatier\Foundation\Tests;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Sabatier\Foundation\Scanner;

/**
 * Tests for src/Scanner.php.
 *
 * No defect was found here — this suite exists because Scanner is the character-level
 * primitive PredicateScanner is built on, so a change to its scanning or to how it
 * advances scanLocation reaches every predicate format string. It pins the behaviour the
 * predicate parser relies on: what each scan accepts, where it leaves the location, and
 * that a failed scan consumes nothing.
 */
final class ScannerTest extends TestCase
{
    /**
     * @return iterable<string, array{string, bool, int, int}>
     */
    public static function integerProvider(): iterable
    {
        yield "plain" => ["42", true, 42, 2];
        yield "negative" => ["-17", true, -17, 3];
        yield "zero" => ["0", true, 0, 1];
        yield "explicit sign" => ["+7", true, 7, 2];
        yield "leading whitespace is skipped" => ["  42  ", true, 42, 4];
        yield "stops at the first non-digit" => ["42abc", true, 42, 2];
        yield "truncates at the decimal point" => ["3.5", true, 3, 1];
        yield "no digits at all" => ["abc", false, 0, 0];
        yield "empty" => ["", false, 0, 0];
    }

    #[DataProvider("integerProvider")]
    public function testScanInt(string $subject, bool $expected, int $value, int $location): void
    {
        $scanner = new Scanner($subject);
        $scanned = 0;

        $this->assertSame($expected, $scanner->scanInt($scanned));
        $this->assertSame($value, $scanned);
        $this->assertSame($location, $scanner->scanLocation, "scanLocation after the scan");
    }

    /**
     * @return iterable<string, array{string, bool, float, int}>
     */
    public static function floatProvider(): iterable
    {
        yield "decimal" => ["2.5", true, 2.5, 3];
        yield "negative decimal" => ["-3.75", true, -3.75, 5];
        yield "integer reads as a float" => ["42", true, 42.0, 2];
        yield "stops at the first non-numeric" => ["2.5abc", true, 2.5, 3];
        yield "leading point" => [".5", true, 0.5, 2];
        yield "exponent" => ["1e3", true, 1000.0, 3];
        yield "not a number" => ["abc", false, 0.0, 0];
        yield "empty" => ["", false, 0.0, 0];
    }

    #[DataProvider("floatProvider")]
    public function testScanFloat(string $subject, bool $expected, float $value, int $location): void
    {
        $scanner = new Scanner($subject);
        $scanned = 0.0;

        $this->assertSame($expected, $scanner->scanFloat($scanned));
        $this->assertSame($value, $scanned);
        $this->assertSame($location, $scanner->scanLocation);
    }

    /**
     * @return iterable<string, array{string, bool, int}>
     */
    public static function hexIntegerProvider(): iterable
    {
        yield "bare hex" => ["ff", true, 255];
        yield "with the 0x prefix" => ["0xFF", true, 255];
        yield "digits read as hex" => ["10", true, 16];
        yield "not hex" => ["zz", false, 0];
    }

    #[DataProvider("hexIntegerProvider")]
    public function testScanHexInt(string $subject, bool $expected, int $value): void
    {
        $scanner = new Scanner($subject);
        $scanned = 0;

        $this->assertSame($expected, $scanner->scanHexInt($scanned));
        $this->assertSame($value, $scanned);
    }

    public function testScanHexFloatReadsTheBinaryExponentForm(): void
    {
        $scanner = new Scanner("0x1.8p1");
        $value = 0.0;

        $this->assertTrue($scanner->scanHexFloat($value));
        $this->assertSame(3.0, $value, "0x1.8 is 1.5, doubled by the p1 exponent");
    }

    public function testScanHexFloatRejectsADecimalWithoutThePrefix(): void
    {
        $scanner = new Scanner("1.8");
        $value = 0.0;

        $this->assertFalse($scanner->scanHexFloat($value));
        $this->assertSame(0, $scanner->scanLocation, "a failed scan consumes nothing");
    }

    public function testScanStringConsumesTheMatch(): void
    {
        $scanner = new Scanner("hola mundo");
        $into = null;

        $this->assertTrue($scanner->scanString("hola", $into));
        $this->assertSame("hola", $into);
        $this->assertSame(4, $scanner->scanLocation);
    }

    public function testScanStringSkipsLeadingWhitespace(): void
    {
        $scanner = new Scanner("  hola");
        $into = null;

        $this->assertTrue($scanner->scanString("hola", $into));
        $this->assertSame(6, $scanner->scanLocation, "the skipped whitespace counts toward the location");
    }

    public function testAFailedScanStringConsumesNothing(): void
    {
        $scanner = new Scanner("hola");
        $into = null;

        $this->assertFalse($scanner->scanString("mundo", $into));
        $this->assertNull($into);
        $this->assertSame(0, $scanner->scanLocation);
    }

    public function testScanningIsCaseInsensitiveByDefault(): void
    {
        $scanner = new Scanner("HOLA");
        $into = null;

        $this->assertTrue($scanner->scanString("hola", $into));
    }

    public function testCaseSensitiveScanningRejectsADifferentCase(): void
    {
        $scanner = new Scanner("HOLA");
        $scanner->caseSensitive = true;
        $into = null;

        $this->assertFalse($scanner->scanString("hola", $into));
    }

    public function testScanUpStringStopsBeforeTheDelimiter(): void
    {
        $scanner = new Scanner("abc|def");
        $into = null;

        $this->assertTrue($scanner->scanUpString("|", $into));
        $this->assertSame("abc", $into);
        $this->assertSame(3, $scanner->scanLocation, "the delimiter itself is not consumed");
    }

    public function testScanCharactersTakesEveryLeadingMember(): void
    {
        $scanner = new Scanner("aaabbb");
        $into = null;

        $this->assertTrue($scanner->scanCharacters("a", $into));
        $this->assertSame("aaa", $into);
        $this->assertSame(3, $scanner->scanLocation);
    }

    public function testScanUpCharactersStopsAtTheFirstMember(): void
    {
        $scanner = new Scanner("abc123");
        $into = null;

        $this->assertTrue($scanner->scanUpCharacters("0123456789", $into));
        $this->assertSame("abc", $into);
        $this->assertSame(3, $scanner->scanLocation);
    }

    public function testTabsAreSkipped(): void
    {
        // charactersToBeSkipped covers the tab, which PredicateScanner deliberately narrows; a change here would silently change what a format string accepts.
        $scanner = new Scanner("\t\t42");
        $value = 0;

        $this->assertTrue($scanner->scanInt($value));
        $this->assertSame(42, $value);
    }

    public function testConsecutiveScansWalkTheWholeString(): void
    {
        $scanner = new Scanner("10 20 30");
        /** @var list<int> $values */
        $values = [];
        $value = 0;

        while ($scanner->scanInt($value)) {
            $values[] = $value;
        }

        $this->assertSame([10, 20, 30], $values);
        $this->assertTrue($scanner->isAtEnd);
    }

    public function testIsAtEndTracksConsumption(): void
    {
        $scanner = new Scanner("ab");
        $into = null;

        $this->assertFalse($scanner->isAtEnd);

        $scanner->scanString("ab", $into);

        $this->assertTrue($scanner->isAtEnd);
    }

    public function testAnEmptyStringIsAtEndImmediately(): void
    {
        $scanner = new Scanner("");
        $value = 0;

        $this->assertTrue($scanner->isAtEnd);
        $this->assertFalse($scanner->scanInt($value));
    }

    public function testScanLocationCanBeRewound(): void
    {
        // PredicateScanner rewinds the location to back out of a candidate parse, so assigning it has to make the same input scannable again.
        $scanner = new Scanner("hola mundo");
        $into = null;
        $scanner->scanString("hola", $into);

        $scanner->scanLocation = 0;

        $this->assertTrue($scanner->scanString("hola", $into));
    }
}
