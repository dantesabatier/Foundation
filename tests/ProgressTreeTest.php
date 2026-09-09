<?php

declare(strict_types=1);

namespace Sabatier\Foundation\Tests;

use PHPUnit\Framework\TestCase;
use Sabatier\Foundation\Progress;
use Sabatier\Foundation\URL;

/**
 * Tests the parts of src/Progress.php ProgressTest does not reach: the factory that
 * attaches a child to a parent, the KVC accessors, and the API surface that is present
 * but unimplemented.
 *
 * Regression guards:
 *  - progress() attaches the new instance to the parent with the pending unit count it
 *    was given, so completing the child moves the parent by that share;
 *  - valueForKey()/setValueForKey() reach the unit counts directly, which is what makes
 *    a progress object observable through a key path;
 *  - the current-progress and publishing API — current(), becomeCurrent(),
 *    resignCurrent(), publish(), unpublish(), addSubscriber(), removeSubscriber() — are
 *    stubs with empty bodies. Cocoa implements them against a per-thread current
 *    progress and a cross-process publishing registry; neither exists here. These tests
 *    pin that so implementing them becomes a deliberate change rather than a surprise.
 */
final class ProgressTreeTest extends TestCase
{
    public function testAStandaloneProgressStartsEmpty(): void
    {
        $progress = Progress::progress(100.0);

        $this->assertSame(100.0, $progress->totalUnitCount);
        $this->assertSame(0.0, $progress->completedUnitCount);
        $this->assertSame(0.0, $progress->fractionCompleted);
        $this->assertFalse($progress->isFinished);
    }

    public function testCompletingAChildMovesItsShareOfTheParent(): void
    {
        $parent = Progress::progress(100.0);
        $child = Progress::progress(10.0, $parent, 50.0);

        $child->completedUnitCount = 10.0;

        $this->assertSame(0.5, $parent->fractionCompleted, "the child was worth half of the parent");
        $this->assertTrue($child->isFinished);
    }

    public function testAPartiallyCompleteChildMovesTheParentProportionally(): void
    {
        $parent = Progress::progress(100.0);
        $child = Progress::progress(10.0, $parent, 50.0);

        $child->completedUnitCount = 5.0;

        $this->assertSame(0.25, $parent->fractionCompleted, "half of a half");
    }

    public function testKeyValueAccessReachesTheUnitCounts(): void
    {
        $progress = Progress::progress(100.0);

        $this->assertSame(100.0, $progress->valueForKey("totalUnitCount"));
        $this->assertSame(0.0, $progress->valueForKey("completedUnitCount"));

        $progress->setValueForKey(75.0, "completedUnitCount");

        $this->assertSame(75.0, $progress->completedUnitCount);
        $this->assertSame(0.75, $progress->fractionCompleted);
    }

    public function testTheCurrentProgressApiIsUnimplemented(): void
    {
        $progress = Progress::progress(100.0);

        $this->assertNull(Progress::current(), "there is no per-thread current progress");

        $progress->becomeCurrent(1.0);

        $this->assertNull(Progress::current(), "becoming current does not register anything");

        $progress->resignCurrent();

        $this->assertNull(Progress::current());
    }

    public function testThePublishingApiIsUnimplemented(): void
    {
        $progress = Progress::progress(100.0);

        $progress->publish();
        $progress->unpublish();
        Progress::removeSubscriber(null);

        $this->assertNull(Progress::addSubscriber(new URL("file:///tmp/watched"), fn(): null => null), "no proxy is handed back");
    }
}
