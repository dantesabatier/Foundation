<?php

/**
 * Created by PhpStorm.
 * User: dante
 * Date: 10/06/20
 * Time: 00:41
 */

declare(strict_types=1);

namespace Sabatier\Foundation;

use Closure;
use Override;
use Throwable;

/**
 * A queue that regulates the execution of operations.
 *
 * An operation queue executes its queued Operation objects based on their priority and readiness.
 * After being added to an operation queue, an operation remains in its queue until it reports that it is finished with its task.
 * You can't directly remove an operation from a queue after it has been added.
 */
final class OperationQueue extends ObjectClass
{
    /** @var int The default maximum number of operations to invoke concurrently in a queue. */
    public const int defaultMaxConcurrentOperationCount = 1;
    private static ?OperationQueue $main = null;
    /**
     * The queue whose operation is running on the current fiber, or null outside any operation.
     * Set and restored by Operation::start() around each operation's execution, so nested
     * queues (an operation that drives another queue) unwind to the right caller.
     * @internal
     */
    public static ?OperationQueue $current = null;
    /** @var ArrayClass<Operation> $operations The operations currently in the queue. */
    private(set) ArrayClass $operations {
        get => $this->operations ??= new ArrayClass();
    }
    /** @var int The maximum number of queued operations that can run at the same time. */
    public int $maxConcurrentOperationCount = self::defaultMaxConcurrentOperationCount;
    /** @var string|null The name of the operation queue. */
    public ?string $name = null;
    #[Override]
    public string $description {
        get => sprintf("<%s %s>", $this->class, $this->name ?? $this->hash);
    }

    /**
     * Returns the operation queue associated with the main thread.
     * @return OperationQueue The default operation queue bound to the main thread.
     */
    public static function main(): OperationQueue
    {
        return self::$main ??= new OperationQueue();
    }

    /**
     * Returns the operation queue that launched the current operation.
     *
     * You can use this method from within a running operation object to get a reference to the operation queue that started it.
     * Calling this method from outside the context of a running operation typically results in null being returned.
     * @return OperationQueue|null The operation queue that started the operation or null if the queue could not be determined.
     */
    public static function current(): ?OperationQueue
    {
        return self::$current;
    }

    /**
     * Adds the specified operation to the receiver.
     *
     * Once added, the specified operation remains in the queue until it finishes executing.
     * @param Operation $operation The operation to be added to the queue.
     * @throws Throwable
     */
    public function addOperation(Operation $operation): void
    {
        !$operation->isExecuting ?: fatal_error("Operation is already executing.");
        !$operation->isFinished ?: fatal_error("Operation is already finished.");
        $this->operations->append($operation);
        $this->operations->sort(fn(Operation $op0, Operation $op1): int => $op1->queuePriority->value <=> $op0->queuePriority->value);
        $operation->observe("isFinished", KeyValueObservingOptions::new, function (Operation $operation, KeyValueObservedChange $change): void {
            if ($change->newValue) {
                $this->operations->remove($operation);
            }
        });
        // A canceled operation never reports isFinished, so without this it would linger forever
        // and hang waitUntilAllOperationsAreFinished. Only drop it if it has not started running.
        $operation->observe("isCancelled", KeyValueObservingOptions::new, function (Operation $operation, KeyValueObservedChange $change): void {
            if ($change->newValue && !$operation->isExecuting) {
                $this->operations->remove($operation);
            }
        });
        $operation->queue = $this;
        if ($operation->isCancelled) {
            // Already canceled before being added: the isCancelled observer never fires (no
            // change), so drop it here instead.
            $this->operations->remove($operation);
            return;
        }
        if (!$operation->isReady) {
            $operation->observe("isReady", KeyValueObservingOptions::new, function (Operation $operation, KeyValueObservedChange $change): void {
                if ($change->newValue) {
                    $this->schedule();
                }
            });
            $this->addOperations($operation->dependencies);
        }
    }

    /**
     * @throws Throwable
     */
    private function schedule(): void
    {
        $executing = $this->operations->filter(fn(Operation $op): bool => $op->isExecuting)->count;
        if ($executing < $this->maxConcurrentOperationCount) {
            // Snapshot the backing array: start() below can finish an operation synchronously,
            // whose isFinished observer removes it from $operations mid-iteration.
            foreach ($this->operations->array as $operation) {
                if ($operation->isReady && !$operation->isExecuting && !$operation->isFinished && !$operation->isCancelled) {
                    $operation->start();
                    $executing++;
                    if ($executing >= $this->maxConcurrentOperationCount) {
                        break;
                    }
                }
            }
        }
        // One resume per suspended fiber, then return: looping until none are suspended would
        // spin at 100% CPU on a fiber that parks on an event that never arrives. Snapshot via
        // ->array because a resumed fiber can finish and its observer then mutates $operations.
        foreach ($this->operations->array as $operation) {
            if ($operation->isExecuting && $operation->fiber?->isSuspended()) {
                $operation->fiber->resume();
            }
        }
    }

    /**
     * Adds the specified operations to the queue.
     *
     * An operation object can be in at most one operation queue at a time and cannot be added if it is currently executing or finished.
     * This method throws an InvalidArgumentException exception if any of those error conditions are true for any of the operations in the $operations parameter.
     * Once added, the specified operation remains in the queue until its isFinished method returns true.
     * @param ArrayClass<Operation> $operations The operations to be added to the queue.
     * @param bool $waitUntilFinished If true, the current thread is blocked until all the specified operations finish executing. If false, the operations are added to the queue and control returns immediately to the caller.
     * @throws Throwable
     */
    public function addOperations(ArrayClass $operations, bool $waitUntilFinished = false): void
    {
        $operations->forEach(fn(Operation $operation) => $this->addOperation($operation));
        $this->schedule();
        if ($waitUntilFinished) {
            $this->waitUntilAllOperationsAreFinished();
        }
    }

    /**
     * Wraps the specified block in an operation and adds it to the receiver.
     * This method adds a single block to the receiver by first wrapping it in an operation object.
     * You should not attempt to get a reference to the newly created operation object or determine its type information.
     * @param Closure(): void $block The block to execute from the operation. The block takes no parameters and has no return value.
     * @throws Throwable
     */
    public function addOperationWithBlock(Closure $block): void
    {
        $this->addOperation(new BlockOperation($block));
        $this->schedule();
    }

    /**
     * Cancels all queued and executing operations.
     *
     * This method calls the {@see Operation::cancel()} method on all operations currently in the queue.
     */
    public function cancelAllOperations(): void
    {
        $this->operations->forEach(fn(Operation $operation) => $operation->cancel());
    }

    /**
     * Blocks the current thread until all the receiver's queued and executing operations finish executing.
     * @throws Throwable
     */
    public function waitUntilAllOperationsAreFinished(): void
    {
        for (;;) {
            $this->schedule();
            if ($this->operations->isEmpty) {
                break;
            }
            // Yield the core between passes: schedule() may be waiting on operations whose
            // fibers are parked on external I/O, and spinning here would peg one core at 100%.
            usleep(1000);
        }
    }
}
