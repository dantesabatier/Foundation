<?php

declare(strict_types=1);

namespace Sabatier\Foundation\Tests;

use PHPUnit\Framework\TestCase;
use Sabatier\Foundation\Dictionary;
use Sabatier\Foundation\InternalInconsistencyException;
use Sabatier\Foundation\Progress;
use Sabatier\Foundation\ProgressFraction;

/**
 * Tests for src/Progress.php and its internal src/ProgressFraction.php.
 *
 * Regression guards:
 *  - ProgressFraction::multiply()/divide() used to route through math(), which is a
 *    common-denominator (add/subtract) engine, so "0.333 * 0.75" returned 0.75 instead
 *    of 0.25; fraction multiply is (n1*n2)/(d1*d2) and division is multiply-by-reciprocal,
 *    neither of which needs a common denominator;
 *  - fromDouble() returned [numerator, numerator] instead of [numerator, denominator],
 *    so every fraction built from a double collapsed to fractionCompleted == 1.0;
 *  - greatestCommonDivisor() used the integer "%" operator and a strict "!== 0" loop
 *    guard on float operands, mixing int/float semantics; it now uses fmod()/loose "!=";
 *  - Progress::fractionCompleted ignored childFraction whenever the receiver had a
 *    positive totalUnitCount, so a parent's rollup only moved when a child *finished*,
 *    never for intermediate child progress; it now reads overallFraction();
 *  - the four arithmetic overflow closures returned [] and would fatal on destructuring
 *    if the overflow branch were ever reached; they now report non-finite results;
 *  - performAsCurrent() returned null instead of running (and returning) its work block.
 */
final class ProgressTest extends TestCase
{
    // MARK: - ProgressFraction arithmetic

    public function testFromDoubleKeepsTheFixedDenominator(): void
    {
        $fraction = ProgressFraction::fraction(0.25);
        $this->assertSame(0.25, $fraction->fractionCompleted, "a fraction built from 0.25 completes a quarter of the way, not fully");
        $this->assertSame(131072.0, $fraction->total, "fromDouble keeps the fixed 131072 denominator");
        $this->assertSame(32768.0, $fraction->completed, "the numerator scales the double against that denominator");
    }

    public function testAddUsesACommonDenominator(): void
    {
        $sum = (new ProgressFraction(1.0, 4.0))->add(new ProgressFraction(1.0, 4.0));
        $this->assertSame(0.5, $sum->fractionCompleted, "a quarter plus a quarter is a half");
    }

    public function testSubtract(): void
    {
        $difference = (new ProgressFraction(3.0, 4.0))->subtract(new ProgressFraction(1.0, 4.0));
        $this->assertSame(0.5, $difference->fractionCompleted, "three quarters minus a quarter is a half");
    }

    public function testMultiplyIsTrueFractionMultiplicationNotCommonDenominator(): void
    {
        $this->assertSame(0.25, (new ProgressFraction(1.0, 2.0))->multiply(new ProgressFraction(1.0, 2.0))->fractionCompleted, "one half times one half is a quarter");
        $this->assertSame(0.5, (new ProgressFraction(2.0, 4.0))->multiply(new ProgressFraction(2.0, 2.0))->fractionCompleted, "one half times one is one half");
        $product = (new ProgressFraction(1.0, 3.0))->multiply(new ProgressFraction(3.0, 4.0));
        $this->assertEqualsWithDelta(0.25, $product->fractionCompleted, 1e-9, "a third times three quarters is a quarter, not three quarters");
    }

    public function testDivideIsMultiplicationByTheReciprocal(): void
    {
        $this->assertSame(0.5, (new ProgressFraction(1.0, 4.0))->divide(new ProgressFraction(1.0, 2.0))->fractionCompleted, "a quarter divided by a half is a half");
        $this->assertEqualsWithDelta(2.0, (new ProgressFraction(1.0, 2.0))->divide(new ProgressFraction(1.0, 4.0))->fractionCompleted, 1e-9, "a half divided by a quarter is two");
    }

    public function testMultiplyByAZeroTotalFractionIsEmpty(): void
    {
        $product = (new ProgressFraction(1.0, 2.0))->multiply(new ProgressFraction());
        $this->assertSame(0.0, $product->completed, "multiplying by a zero-total fraction yields an empty fraction");
        $this->assertSame(0.0, $product->total, "multiplying by a zero-total fraction yields an empty fraction");
    }

    public function testDivideByAZeroCompletedFractionIsEmpty(): void
    {
        $quotient = (new ProgressFraction(1.0, 2.0))->divide(new ProgressFraction(0.0, 4.0));
        $this->assertSame(0.0, $quotient->total, "dividing by a fraction with no completed work yields an empty fraction rather than dividing by zero");
    }

    public function testAddingTwoZeroTotalFractionsIsAFatalError(): void
    {
        $this->expectException(InternalInconsistencyException::class);
        (new ProgressFraction())->add(new ProgressFraction());
    }

    public function testIndeterminateAndFinishedFlags(): void
    {
        $this->assertTrue((new ProgressFraction(0.0, 0.0))->isIndeterminate, "a zero/zero fraction is indeterminate");
        $this->assertTrue((new ProgressFraction(-1.0, 4.0))->isIndeterminate, "a negative completed count is indeterminate");
        $this->assertFalse((new ProgressFraction(1.0, 4.0))->isIndeterminate, "a positive proper fraction is determinate");
        $this->assertTrue((new ProgressFraction(4.0, 4.0))->isFinished, "completed equal to total is finished");
        $this->assertFalse((new ProgressFraction(0.0, 4.0))->isFinished, "nothing completed is not finished");
    }

    public function testEqualityComparesBothComponents(): void
    {
        $this->assertTrue((new ProgressFraction(1.0, 2.0))->isEqual(new ProgressFraction(1.0, 2.0)), "fractions with matching components are equal");
        $this->assertFalse((new ProgressFraction(1.0, 2.0))->isEqual(new ProgressFraction(1.0, 4.0)), "fractions with different totals are not equal");
        $this->assertFalse((new ProgressFraction(1.0, 2.0))->isEqual("1/2"), "a non-fraction is never equal");
    }

    // MARK: - Progress: standalone unit counts

    public function testDiscreteProgressFractionCompleted(): void
    {
        $progress = Progress::discreteProgress(4.0);
        $this->assertSame(0.0, $progress->fractionCompleted, "no work completed is zero");
        $progress->completedUnitCount = 1.0;
        $this->assertSame(0.25, $progress->fractionCompleted, "one of four units is a quarter");
        $progress->completedUnitCount = 4.0;
        $this->assertSame(1.0, $progress->fractionCompleted, "four of four units is fully complete");
        $this->assertTrue($progress->isFinished, "reaching the total unit count finishes the progress");
    }

    public function testZeroTotalIsIndeterminate(): void
    {
        $progress = Progress::discreteProgress(0.0);
        $this->assertTrue($progress->isIndeterminate, "a zero total unit count is indeterminate");
        $this->assertSame(0.0, $progress->fractionCompleted, "an indeterminate progress reports zero completion");
        $this->assertFalse($progress->isFinished, "an indeterminate progress is not finished");
    }

    // MARK: - Progress: parent/child rollup

    public function testSingleChildRollupTracksIntermediateProgress(): void
    {
        $parent = Progress::discreteProgress(2.0);
        $child = new Progress();
        $child->totalUnitCount = 4.0;
        $parent->addChild($child, 2.0);

        $child->completedUnitCount = 2.0;
        $this->assertSame(0.5, $parent->fractionCompleted, "a child that is halfway through the whole portion moves the parent to one half");

        $child->completedUnitCount = 4.0;
        $this->assertSame(1.0, $parent->fractionCompleted, "a finished child that owns the whole portion finishes the parent");
        $this->assertTrue($parent->isFinished, "the parent is finished once its only child completes its full portion");
    }

    public function testTwoChildrenSplitTheParentPortion(): void
    {
        $parent = Progress::discreteProgress(10.0);
        $first = new Progress();
        $first->totalUnitCount = 100.0;
        $second = new Progress();
        $second->totalUnitCount = 100.0;
        $parent->addChild($first, 5.0);
        $parent->addChild($second, 5.0);

        $first->completedUnitCount = 100.0;
        $this->assertSame(0.5, $parent->fractionCompleted, "one of two equally weighted children finishing moves the parent halfway");

        $second->completedUnitCount = 50.0;
        $this->assertSame(0.75, $parent->fractionCompleted, "the second child halfway through its portion adds another quarter");

        $second->completedUnitCount = 100.0;
        $this->assertSame(1.0, $parent->fractionCompleted, "both children finishing completes the parent");
    }

    public function testAddingAChildThatAlreadyHasAParentIsAFatalError(): void
    {
        $first = Progress::discreteProgress(1.0);
        $second = Progress::discreteProgress(1.0);
        $child = new Progress();
        $child->totalUnitCount = 1.0;
        $first->addChild($child, 1.0);

        $this->expectException(InternalInconsistencyException::class);
        $second->addChild($child, 1.0);
    }

    // MARK: - Progress: cancel / pause / resume propagation

    public function testCancelRequiresCancellableAndRunsHandlerOnce(): void
    {
        $progress = Progress::discreteProgress(1.0);
        $calls = 0;
        $progress->cancellationHandler = function () use (&$calls): void {
            $calls++;
        };

        $progress->cancel();
        $this->assertFalse($progress->isCancelled, "a non-cancellable progress ignores cancel()");
        $this->assertSame(0, $calls, "the cancellation handler does not run for a non-cancellable progress");

        $progress->isCancellable = true;
        $progress->cancel();
        $progress->cancel();
        $this->assertTrue($progress->isCancelled, "a cancellable progress becomes cancelled");
        $this->assertSame(1, $calls, "the cancellation handler runs exactly once even across repeated cancel() calls");
    }

    public function testCancelPropagatesToChildren(): void
    {
        $parent = Progress::discreteProgress(1.0);
        $parent->isCancellable = true;
        $child = new Progress();
        $child->totalUnitCount = 1.0;
        $child->isCancellable = true;
        $parent->addChild($child, 1.0);

        $parent->cancel();
        $this->assertTrue($child->isCancelled, "cancelling a parent cancels its children");
    }

    public function testAddingAChildToAnAlreadyCancelledParentCancelsIt(): void
    {
        $parent = Progress::discreteProgress(1.0);
        $parent->isCancellable = true;
        $parent->cancel();

        $child = new Progress();
        $child->totalUnitCount = 1.0;
        $child->isCancellable = true;
        $parent->addChild($child, 1.0);
        $this->assertTrue($child->isCancelled, "a child added to a cancelled parent is cancelled on insertion");
    }

    public function testPauseAndResumePropagateToChildren(): void
    {
        $parent = Progress::discreteProgress(1.0);
        $parent->isPausable = true;
        $child = new Progress();
        $child->totalUnitCount = 1.0;
        $child->isPausable = true;
        $parent->addChild($child, 1.0);

        $parent->pause();
        $this->assertTrue($parent->isPaused, "a pausable parent pauses");
        $this->assertTrue($child->isPaused, "pausing a parent pauses its children");

        $parent->resume();
        $this->assertFalse($parent->isPaused, "resuming clears the parent's paused state");
        $this->assertFalse($child->isPaused, "resuming a parent resumes its children");
    }

    public function testPauseRunsHandlerOnlyWhilePausable(): void
    {
        $progress = Progress::discreteProgress(1.0);
        $paused = 0;
        $resumed = 0;
        $progress->pausingHandler = function () use (&$paused): void {
            $paused++;
        };
        $progress->resumingHandler = function () use (&$resumed): void {
            $resumed++;
        };

        $progress->pause();
        $this->assertFalse($progress->isPaused, "a non-pausable progress ignores pause()");
        $this->assertSame(0, $paused, "the pausing handler does not run for a non-pausable progress");

        $progress->isPausable = true;
        $progress->pause();
        $progress->pause();
        $progress->resume();
        $progress->resume();
        $this->assertSame(1, $paused, "the pausing handler runs once across repeated pause() calls");
        $this->assertSame(1, $resumed, "the resuming handler runs once across repeated resume() calls");
    }

    // MARK: - Progress: user info and misc

    public function testUserInfoObjectRoundTrips(): void
    {
        $progress = Progress::discreteProgress(1.0);
        $progress->setUserInfoObject("value", "key");
        $this->assertSame("value", $progress->userInfo["key"], "setUserInfoObject stores the value under its key");
        $progress->setUserInfoObject(null, "key");
        $this->assertNull($progress->userInfo["key"], "passing null removes the entry");
    }

    public function testConstructorSeedsTheUserInfoDictionary(): void
    {
        $userInfo = new Dictionary(["seed" => 1]);
        $progress = new Progress(null, $userInfo);
        $this->assertSame(1, $progress->userInfo["seed"], "the supplied user info dictionary seeds the progress");
    }

    public function testPerformAsCurrentRunsAndReturnsTheWorkBlock(): void
    {
        $progress = Progress::discreteProgress(1.0);
        $ran = false;
        $result = $progress->performAsCurrent(1.0, function () use (&$ran): int {
            $ran = true;
            return 42;
        });
        $this->assertTrue($ran, "performAsCurrent executes the work block");
        $this->assertSame(42, $result, "performAsCurrent returns the work block's value");
    }
}
