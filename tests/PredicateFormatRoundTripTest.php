<?php

declare(strict_types=1);

namespace Sabatier\Foundation\Tests;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Sabatier\Foundation\Dictionary;
use Sabatier\Foundation\Predicates\Predicate;

/**
 * Tests that predicateFormat reads back as the predicate it came from.
 *
 * Regression guard:
 *  - the comparison options were re-emitted only by the string operators. CONTAINS,
 *    BEGINSWITH, ENDSWITH, LIKE and MATCHES extend StringPredicateOperator, which
 *    overrode $symbol to append the bracketed letters; == , != and IN extend
 *    PredicateOperator directly and printed the bare symbol. So "s ==[cd] \"JOSE\"" read
 *    back as "s = 'JOSE'" — a format that looks case-sensitive while the predicate still
 *    matched "josé", and which re-parsed into a different predicate than the one it
 *    described. The suffix is built by one shared method now, and the two operators that
 *    were missing it override $symbol to use it.
 */
final class PredicateFormatRoundTripTest extends TestCase
{
    /**
     * @return iterable<string, array{string, string}>
     */
    public static function modifierProvider(): iterable
    {
        yield "equal, case" => ["s ==[c] \"X\"", "[c]"];
        yield "equal, case and diacritic" => ["s ==[cd] \"X\"", "[cd]"];
        yield "not equal" => ["s !=[cd] \"X\"", "[cd]"];
        yield "in" => ["s IN[cd] {\"X\"}", "[cd]"];
        yield "contains" => ["s CONTAINS[cd] \"X\"", "[cd]"];
        yield "beginswith" => ["s BEGINSWITH[c] \"X\"", "[c]"];
        yield "endswith" => ["s ENDSWITH[cd] \"X\"", "[cd]"];
        yield "like" => ["s LIKE[c] \"X\"", "[c]"];
        yield "matches" => ["s MATCHES[c] \"X\"", "[c]"];
    }

    #[DataProvider("modifierProvider")]
    public function testTheFormatCarriesTheModifier(string $format, string $modifier): void
    {
        $this->assertStringContainsString($modifier, Predicate::format($format)->predicateFormat);
    }

    /**
     * @return iterable<string, array{string, string}>
     */
    public static function plainProvider(): iterable
    {
        // An operator with no options must print exactly as before — the suffix appears only when there is something to report.
        yield "equal" => ["s == \"X\"", "s = 'X'"];
        yield "not equal" => ["s != \"X\"", "s != 'X'"];
        yield "less than" => ["s < 5", "s < 5"];
        yield "greater than" => ["s > 5", "s > 5"];
        yield "less than or equal" => ["s <= 5", "s <= 5"];
        yield "between" => ["n BETWEEN {1, 5}", "n BETWEEN {1, 5}"];
        yield "in" => ["s IN {\"X\"}", "s IN {'X'}"];
        yield "contains" => ["s CONTAINS \"X\"", "s CONTAINS 'X'"];
    }

    #[DataProvider("plainProvider")]
    public function testAnOperatorWithoutOptionsPrintsUnchanged(string $format, string $expected): void
    {
        $this->assertSame($expected, Predicate::format($format)->predicateFormat);
    }

    /**
     * @return iterable<string, array{string, bool}>
     */
    public static function semanticProvider(): iterable
    {
        // Evaluated against "josé", so the modifier decides the answer.
        yield "case and diacritic insensitive matches" => ["s ==[cd] \"JOSE\"", true];
        yield "case insensitive alone does not" => ["s ==[c] \"JOSE\"", false];
        yield "no modifier does not" => ["s == \"JOSE\"", false];
        yield "in, case and diacritic insensitive" => ["s IN[cd] {\"JOSE\"}", true];
        yield "contains, case and diacritic insensitive" => ["s CONTAINS[cd] \"OS\"", true];
    }

    /**
     * The point of the round-trip: re-parsing the format has to produce a predicate that
     * answers the same thing. Before the fix, "s ==[cd] …" printed without its options and
     * the re-parsed predicate was case-sensitive.
     */
    #[DataProvider("semanticProvider")]
    public function testReparsingTheFormatPreservesTheAnswer(string $format, bool $expected): void
    {
        $object = new Dictionary(["s" => "josé"]);
        $original = Predicate::format($format);

        $this->assertSame($expected, $original->evaluate($object), "the original predicate");

        $reparsed = Predicate::format($original->predicateFormat);

        $this->assertSame($expected, $reparsed->evaluate($object), "the predicate re-parsed from its own format");
    }

    public function testTheFormatIsStableAcrossASecondRoundTrip(): void
    {
        // Parsing the format again must yield the same format, or the representation is still losing something.
        $first = Predicate::format("s ==[cd] \"JOSE\"")->predicateFormat;
        $second = Predicate::format($first)->predicateFormat;

        $this->assertSame($first, $second);
    }
}
