<?php

declare(strict_types=1);

namespace Sabatier\Foundation\Tests;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Sabatier\Foundation\Date;
use Sabatier\Foundation\Predicates\PredicateUtilities;

/**
 * Tests the function surface src/Predicates/PredicateUtilities.php exposes to predicate
 * format strings — the casts, the date helpers and the string transforms that
 * FunctionExpression dispatches to. PredicateUtilitiesTest covers the aggregates;
 * these are the scalar ones it does not reach.
 *
 * Regression guards:
 *  - isReserved() is case-insensitive, since a format string may spell a keyword in any
 *    case and the scanner has to recognise it either way;
 *  - cast() with a null type is the identity, and every numeric alias pair — int and
 *    integer, float and double — resolves to the same conversion;
 *  - the date helpers propagate null rather than substituting the epoch, so a missing
 *    date stays missing through a key path;
 *  - canonical() strips diacritics and leaves the case alone, which is what the
 *    diacritic-insensitive comparison operators rely on.
 */
final class PredicateUtilitiesFunctionTest extends TestCase
{
    private string $timeZone;

    protected function setUp(): void
    {
        // The date helpers render through the process time zone, so the formatted output is only predictable once it is pinned.
        $this->timeZone = date_default_timezone_get();
        date_default_timezone_set("UTC");
    }

    protected function tearDown(): void
    {
        date_default_timezone_set($this->timeZone);
    }

    /** @return iterable<string, array{string, bool}> */
    public static function reservedWordProvider(): iterable
    {
        yield "lowercase keyword" => ["and", true];
        yield "uppercase keyword" => ["AND", true];
        yield "mixed case keyword" => ["BeginsWith", true];
        yield "predicate literal" => ["truepredicate", true];
        yield "ordinary identifier" => ["salary", false];
        yield "empty" => ["", false];
    }

    #[DataProvider("reservedWordProvider")]
    public function testReservedWordsAreRecognisedInAnyCase(string $word, bool $expected): void
    {
        $this->assertSame($expected, PredicateUtilities::isReserved($word));
    }

    public function testARandomNumberStaysWithinItsBound(): void
    {
        for ($attempt = 0; $attempt < 20; $attempt++) {
            $value = PredicateUtilities::random(10)->intValue;

            $this->assertGreaterThanOrEqual(0, $value);
            $this->assertLessThanOrEqual(10, $value);
        }
    }

    public function testCastWithoutATypeIsTheIdentity(): void
    {
        $this->assertSame("5", PredicateUtilities::cast("5"));
        $this->assertSame(5, PredicateUtilities::cast(5));
        $this->assertNull(PredicateUtilities::cast(null));
    }

    /** @return iterable<string, array{mixed, string, mixed}> */
    public static function castProvider(): iterable
    {
        yield "to string" => [5, "string", "5"];
        yield "to int" => ["42", "int", 42];
        yield "to integer" => ["42", "integer", 42];
        yield "to float" => ["1.5", "float", 1.5];
        yield "to double" => ["1.5", "double", 1.5];
    }

    #[DataProvider("castProvider")]
    public function testCastConvertsToTheNamedType(mixed $value, string $type, mixed $expected): void
    {
        $this->assertSame($expected, PredicateUtilities::cast($value, $type));
    }

    public function testDateHelpersFormatAKnownInstant(): void
    {
        $date = Date::dateWithTimeIntervalSince1970(1700000000.0);

        $this->assertSame("2023-11-14", PredicateUtilities::date($date));
        $this->assertSame("2023", PredicateUtilities::dateFormat($date, "Y"));
        $this->assertSame("2023-11-14 22:13:20", PredicateUtilities::dateFormat($date));
    }

    public function testAMissingDateStaysMissing(): void
    {
        $this->assertNull(PredicateUtilities::date(null), "a null date does not become the epoch");
        $this->assertNull(PredicateUtilities::dateFormat(null));
    }

    public function testTheCurrentInstantIsReportedInBothShapes(): void
    {
        $this->assertInstanceOf(Date::class, PredicateUtilities::now());
        $this->assertMatchesRegularExpression("/^\d{4}-\d{2}-\d{2}$/", (string)PredicateUtilities::currentDate());
    }

    public function testCanonicalStripsDiacriticsAndKeepsCase(): void
    {
        $this->assertSame("AEI N", PredicateUtilities::canonical("ÁÉÍ Ñ"));
        $this->assertSame("Resume", PredicateUtilities::canonical("Résumé"), "the case is left alone; only the diacritics go");
    }

    public function testRegularExpressionReplacementRewritesEveryMatch(): void
    {
        $this->assertSame("a#b#", PredicateUtilities::regexpReplace("a1b2", "/\d/", "#"));
        $this->assertSame("abc", PredicateUtilities::regexpReplace("abc", "/\d/", "#"), "a pattern that matches nothing leaves the subject alone");
    }
}
