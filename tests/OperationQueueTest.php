<?php

declare(strict_types=1);

namespace Sabatier\Foundation\Tests;

use Fiber;
use Override;
use PHPUnit\Framework\TestCase;
use Sabatier\Foundation\ArrayClass;
use Sabatier\Foundation\Operation;
use Sabatier\Foundation\OperationQueue;
use Sabatier\Foundation\OperationQueuePriority;

/**
 * A minimal concurrent operation whose body is a caller-supplied closure, letting each
 * test drive the fiber lifecycle (including Fiber::suspend) without a full task class.
 */
final class RecordingOperation extends Operation
{
    /** @param \Closure(RecordingOperation): void $body */
    public function __construct(private readonly \Closure $body)
    {
    }

    #[Override]
    public function main(): void
    {
        ($this->body)($this);
    }
}

/**
 * Tests for src/OperationQueue.php.
 *
 * Regression guards:
 *  - addOperation sorted operations by ascending priority value (`$op0 <=> $op1`), so the
 *    lowest-priority operation was scheduled first. NSOperationQueue starts the
 *    highest-priority ready operation first; the comparator must sort descending.
 *  - schedule() drained suspended fibers inside `while ($operations->contains(fiber
 *    isSuspended))`. A fiber that parks on an external event (or re-suspends on every
 *    resume) turned that loop into an infinite 100%-CPU spin, breaking the documented
 *    immediate-return contract of addOperation/addOperationWithBlock. Each suspended fiber
 *    must now get one resume per pass, after which control returns to the caller.
 *  - a cancelled operation was never removed from the queue: cancel() does not set
 *    isFinished, so the isFinished observer never fired and the operation lingered forever,
 *    hanging waitUntilAllOperationsAreFinished. Cancelling a not-yet-executing operation
 *    (or adding an already-cancelled one) must drop it from the queue.
 *  - current() scanned a global ArrayClass of every queue ever created (a strong registry
 *    that also leaked every queue, since __destruct could never run). It now reads a static
 *    OperationQueue::$current that Operation::start() sets around each operation and restores
 *    afterwards, so it reports the running operation's queue and null outside any operation.
 */
final class OperationQueueTest extends TestCase
{
    /**
     * OperationQueue::$current is process-global static state. A test that intentionally leaves
     * a fiber parked (testScheduleReturnsWhenAFiberStaysParked) never runs the finally that
     * restores it, so it would bleed into later tests sharing the same PHPUnit process. Reset it
     * between tests to keep current() assertions independent of execution order.
     */
    #[Override]
    protected function tearDown(): void
    {
        OperationQueue::$current = null;
        parent::tearDown();
    }

    public function testAddOperationWithBlockRunsTheBlock(): void
    {
        $queue = new OperationQueue();
        $ran = false;
        $queue->addOperationWithBlock(function () use (&$ran): void {
            $ran = true;
        });

        $this->assertTrue($ran, "a block operation runs to completion when the queue can start it");
    }

    public function testHigherPriorityOperationIsScheduledFirst(): void
    {
        $queue = new OperationQueue();
        // Serialize execution so scheduling order — not concurrency — decides who runs first.
        $queue->maxConcurrentOperationCount = 1;

        $order = [];
        $low = new RecordingOperation(function () use (&$order): void {
            $order[] = "low";
        });
        $low->queuePriority = OperationQueuePriority::low;
        $high = new RecordingOperation(function () use (&$order): void {
            $order[] = "high";
        });
        $high->queuePriority = OperationQueuePriority::high;

        // Add low first: only the priority sort, not insertion order, may reorder them.
        $queue->addOperation($low);
        $queue->addOperation($high);
        $queue->waitUntilAllOperationsAreFinished();

        $this->assertSame(["high", "low"], $order, "the higher-priority operation must start before the lower-priority one");
    }

    public function testScheduleResumesASuspendedFiberAndReturns(): void
    {
        $queue = new OperationQueue();
        $started = false;
        $resumed = false;
        $operation = new RecordingOperation(function () use (&$started, &$resumed): void {
            $started = true;
            // Park once. The old `while (…isSuspended())` spin would either loop forever
            // here or, once resumed, keep re-entering; the fixed schedule() grants exactly
            // one resume per pass and then hands control back to the caller.
            Fiber::suspend();
            $resumed = true;
        });

        // If schedule() still spun on `while (…isSuspended())`, this call would never return.
        $queue->addOperations(new ArrayClass([$operation]));

        $this->assertTrue($started, "the operation's fiber started and parked");
        $this->assertTrue($resumed, "schedule() resumed the suspended fiber and returned rather than spinning");
    }

    public function testScheduleReturnsWhenAFiberStaysParked(): void
    {
        $queue = new OperationQueue();
        $reached = false;
        $operation = new RecordingOperation(function () use (&$reached): void {
            // Never resume past the first suspend within a single pass: a second suspend
            // would leave the fiber parked. schedule() must still return control instead of
            // busy-waiting for it to unpark.
            Fiber::suspend();
            Fiber::suspend();
            $reached = true;
        });

        // The assertion that matters is simply that this call terminates.
        $queue->addOperations(new ArrayClass([$operation]));

        $this->assertTrue($operation->isExecuting, "a fiber parked on a later suspend keeps the operation executing");
        $this->assertFalse($reached, "schedule() returned without draining the still-parked fiber to completion");
    }

    public function testCancelledOperationNeverRuns(): void
    {
        $queue = new OperationQueue();
        $queue->maxConcurrentOperationCount = 1;

        $ran = false;
        $operation = new RecordingOperation(function () use (&$ran): void {
            $ran = true;
        });
        $operation->cancel();
        $queue->addOperation($operation);

        // addOperationWithBlock triggers a scheduling pass; the cancelled operation must be
        // skipped by it while the fresh block still runs.
        $blockRan = false;
        $queue->addOperationWithBlock(function () use (&$blockRan): void {
            $blockRan = true;
        });

        $this->assertFalse($ran, "a cancelled operation is skipped by the scheduler");
        $this->assertTrue($blockRan, "a ready operation still runs alongside the skipped cancelled one");
        $this->assertTrue($queue->operations->isEmpty, "an operation cancelled before it was added does not linger in the queue");
    }

    public function testCancellingAnExecutingOperationKeepsItUntilItFinishes(): void
    {
        $queue = new OperationQueue();
        $queue->maxConcurrentOperationCount = 5;

        // This operation starts and then parks (its fiber suspends and never completes here).
        $operation = new RecordingOperation(function (): void {
            Fiber::suspend();
            Fiber::suspend();
        });
        $queue->addOperation($operation);
        // The ready branch of addOperation does not schedule; a block operation forces a pass.
        $queue->addOperationWithBlock(function (): void {
        });

        $this->assertTrue($operation->isExecuting, "the operation started and its fiber parked");

        $operation->cancel();

        // NSOperation semantics: cancelling an already-executing operation does not yank it —
        // it stays in the queue until its fiber reports isFinished. The isCancelled observer
        // must therefore leave executing operations in place.
        $this->assertTrue($operation->isCancelled, "the operation is marked cancelled");
        $this->assertTrue($queue->operations->contains(fn(Operation $op): bool => $op === $operation), "an executing operation stays queued after being cancelled");
    }

    public function testCurrentIsNullOutsideAnyOperation(): void
    {
        $this->assertNull(OperationQueue::current(), "current() is null when no operation is running");
    }

    public function testCurrentReportsTheRunningOperationsQueueAndRestoresAfterwards(): void
    {
        $queue = new OperationQueue();
        $seen = null;
        $queue->addOperationWithBlock(function () use (&$seen): void {
            $seen = OperationQueue::current();
        });
        $queue->waitUntilAllOperationsAreFinished();

        $this->assertSame($queue, $seen, "current() inside a running operation is that operation's queue");
        $this->assertNull(OperationQueue::current(), "current() is restored to null once the operation finishes");
    }

    public function testDependencyRunsBeforeDependent(): void
    {
        $queue = new OperationQueue();
        $queue->maxConcurrentOperationCount = 1;

        $order = [];
        $dependency = new RecordingOperation(function () use (&$order): void {
            $order[] = "dependency";
        });
        $dependent = new RecordingOperation(function () use (&$order): void {
            $order[] = "dependent";
        });
        $dependent->addDependency($dependency);

        // Adding the dependent pulls its dependencies into the queue on its own, so the
        // dependency must not be added a second time.
        $queue->addOperation($dependent);
        $queue->waitUntilAllOperationsAreFinished();

        $this->assertSame(["dependency", "dependent"], $order, "a dependency finishes before the operation that depends on it");
    }

    public function testFinishedOperationsLeaveTheQueue(): void
    {
        $queue = new OperationQueue();
        $queue->addOperationWithBlock(function (): void {
        });
        $queue->waitUntilAllOperationsAreFinished();

        $this->assertTrue($queue->operations->isEmpty, "operations are removed from the queue once they finish");
    }
}
