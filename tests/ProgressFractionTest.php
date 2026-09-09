<?php

declare(strict_types=1);

namespace Sabatier\Foundation\Tests;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Sabatier\Foundation\ProgressFraction;

/**
 * Tests src/ProgressFraction.php, the rational arithmetic Progress uses to combine a
 * tree of unit counts without accumulating floating-point drift.
 *
 * Regression guards:
 *  - a zero total is the additive identity in both directions: combining an empty
 *    fraction with a real one answers the real one rather than raising or producing
 *    a division by zero;
 *  - the three states a fraction can be in are distinguished — indeterminate for a
 *    negative or wholly zero fraction, finished once completed reaches total, and a
 *    zero total with work completed counts as finished rather than indeterminate;
 *  - isEqual() compares the numerator and denominator literally, so 1/2 and 2/4 are
 *    not equal. That is deliberate: the fraction records the unit counts it was given,
 *    and two different unit counts are different progress even at the same ratio.
 *
 * The simplify()/simplified() fallback inside math() is unreachable and stays
 * uncovered: it runs only when leastCommonMultiple() answers zero, and that value is
 * total / gcd(total, other) with a total the guards above have already proved non-zero.
 */
final class ProgressFractionTest extends TestCase
{
    private function fraction(float $completed, float $total): ProgressFraction
    {
        return new ProgressFraction($completed, $total);
    }

    /** @return iterable<string, array{string, float, float, float, float, float}> */
    public static function arithmeticProvider(): iterable
    {
        yield "addition" => ["add", 1.0, 2.0, 1.0, 4.0, 0.75];
        yield "subtraction" => ["subtract", 3.0, 4.0, 1.0, 4.0, 0.5];
        yield "multiplication" => ["multiply", 1.0, 2.0, 1.0, 2.0, 0.25];
        yield "division" => ["divide", 1.0, 2.0, 1.0, 4.0, 2.0];
    }

    #[DataProvider("arithmeticProvider")]
    public function testArithmeticProducesTheExpectedRatio(string $operation, float $completed, float $total, float $otherCompleted, float $otherTotal, float $expected): void
    {
        $result = $this->fraction($completed, $total)->$operation($this->fraction($otherCompleted, $otherTotal));

        $this->assertSame($expected, $result->fractionCompleted);
    }

    public function testAZeroTotalIsTheIdentityInBothDirections(): void
    {
        $empty = $this->fraction(0.0, 0.0);
        $half = $this->fraction(1.0, 2.0);

        $this->assertSame(0.5, $empty->add($half)->fractionCompleted, "an empty left operand answers the right one");
        $this->assertSame(0.5, $half->add($empty)->fractionCompleted, "and an empty right operand answers the left one");
    }

    /** @return iterable<string, array{float, float, bool}> */
    public static function indeterminateProvider(): iterable
    {
        yield "wholly zero" => [0.0, 0.0, true];
        yield "negative completed" => [-1.0, 2.0, true];
        yield "negative total" => [1.0, -2.0, true];
        yield "ordinary" => [1.0, 2.0, false];
    }

    #[DataProvider("indeterminateProvider")]
    public function testIndeterminateCoversTheUnusableFractions(float $completed, float $total, bool $expected): void
    {
        $this->assertSame($expected, $this->fraction($completed, $total)->isIndeterminate);
    }

    /** @return iterable<string, array{float, float, bool}> */
    public static function finishedProvider(): iterable
    {
        yield "complete" => [2.0, 2.0, true];
        yield "past the total" => [3.0, 2.0, true];
        yield "in progress" => [1.0, 2.0, false];
        yield "work with no total" => [1.0, 0.0, true];
        yield "nothing at all" => [0.0, 0.0, false];
    }

    #[DataProvider("finishedProvider")]
    public function testFinishedRequiresTheWorkToHaveLanded(float $completed, float $total, bool $expected): void
    {
        $this->assertSame($expected, $this->fraction($completed, $total)->isFinished);
    }

    public function testTheCompletedFractionHandlesItsEdges(): void
    {
        $this->assertSame(0.0, $this->fraction(0.0, 0.0)->fractionCompleted, "an indeterminate fraction reports no progress");
        $this->assertSame(1.0, $this->fraction(1.0, 0.0)->fractionCompleted, "work with no total is complete");
        $this->assertSame(0.5, $this->fraction(1.0, 2.0)->fractionCompleted);
    }

    public function testAFractionIsBuiltFromADouble(): void
    {
        $fraction = ProgressFraction::fraction(0.5);

        $this->assertSame(0.5, $fraction->fractionCompleted);
        $this->assertFalse($fraction->overflowed);
        $this->assertTrue(ProgressFraction::fraction(0.5, true)->overflowed, "the overflow flag is carried through");
    }

    public function testEqualityComparesTheUnitCountsRatherThanTheRatio(): void
    {
        $half = $this->fraction(1.0, 2.0);

        $this->assertTrue($half->isEqual($this->fraction(1.0, 2.0)));
        $this->assertFalse($half->isEqual($this->fraction(2.0, 4.0)), "the same ratio built from different counts is a different fraction");
        $this->assertFalse($half->isEqual("1/2"), "a string is never a fraction");
    }

    public function testTheDebugDescriptionShowsBothTermsAndTheRatio(): void
    {
        $this->assertSame("1 / 2 (0.5)", $this->fraction(1.0, 2.0)->debugDescription);
    }
}
