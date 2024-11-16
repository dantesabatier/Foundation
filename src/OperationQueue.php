<?php

/**
 * Created by PhpStorm.
 * User: dante
 * Date: 10/06/20
 * Time: 00:41
 */

namespace Sabatier\Foundation;

use Closure;
use Fiber;
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
    /** @var ArrayClass<OperationQueue>|null $queues */
    private static ?ArrayClass $queues = null;
    private static ?OperationQueue $main = null;
    /** @var ArrayClass<Operation> $operations The operations currently in the queue. */
    private(set) ArrayClass $operations;
    /** @var int The maximum number of queued operations that can run at the same time. */
    public int $maxConcurrentOperationCount;
    /** @var string|null The name of the operation queue. */
    public ?string $name = null;
    /** @internal */
    public bool $isCurrentQueue = false;
    public string $description {
        get => sprintf("<%s %s>", self::class, $this->name ?? $this->hash);
    }

    public function __construct()
    {
        $this->operations = new ArrayClass();
        self::allQueues()->append($this);
    }

    public function __destruct()
    {
        self::allQueues()->remove($this);
    }

    public function __clone()
    {
        self::allQueues()->append($this);
    }

    /**
     * @return ArrayClass<OperationQueue>
     */
    private static function allQueues(): ArrayClass
    {
        self::$queues ??= new ArrayClass();
        return self::$queues;
    }

    /**
     * Returns the operation queue associated with the main thread.
     * @return OperationQueue The default operation queue bound to the main thread.
     */
    public static function main(): OperationQueue
    {
        self::$main ??= new OperationQueue();
        return self::$main;
    }

    /**
     * Returns the operation queue that launched the current operation.
     *
     * You can use this method from within a running operation object to get a reference to the operation queue that started it.
     * Calling this method from outside the context of a running operation typically results in nil being returned.
     * @return OperationQueue|null The operation queue that started the operation or nil if the queue could not be determined.
     */
    public static function current(): ?OperationQueue
    {
        return self::allQueues()->first(fn(OperationQueue $queue): bool => $queue->isCurrentQueue);
    }

    /**
     * Adds the specified operation to the receiver.
     *
     * Once added, the specified operation remains in the queue until it finishes executing.
     * @param Operation $operation The operation to be added to the queue.
     */
    public function addOperation(Operation $operation): void
    {
        try {
            $fiber = new Fiber(function () use ($operation): void {
                if ($operation->isExecuting || $operation->isFinished) {
                    fatal_error();
                }
                Fiber::suspend();
                $this->operations[] = $operation;
                $this->operations->sort(fn(Operation $op0, Operation $op1): int => ComparisonResult::orderedAscending->value * ($op0->queuePriority->value <=> $op1->queuePriority->value));
                /** @psalm-suppress UndefinedVariable */
                $observation = $operation->observe("isFinished", KeyValueObservingOptions::new, function (Operation $operation, KeyValueObservedChange $change) use (&$observation): void {
                    $observation->invalidate();
                    if ($change->newValue) {
                        $this->operations->remove($operation);
                    }
                });
                $operation->queue = $this;
                if ($operation->isReady) {
                    $operation->start();
                    return;
                }
                $observation = $operation->observe("isReady", KeyValueObservingOptions::new, function (Operation $operation, KeyValueObservedChange $change) use (&$observation): void {
                    $observation->invalidate();
                    if ($change->newValue) {
                        $operation->start();
                    }
                });
                $this->addOperations($operation->dependencies);
            });
            $fiber->start();
            if (!$fiber->isTerminated()) {
                $fiber->resume();
            }
        } catch (Throwable) {
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
        foreach ($operations as $operation) {
            $this->addOperation($operation);
        }
        if ($waitUntilFinished) {
            $this->waitUntilAllOperationsAreFinished();
        }
    }

    /**
     * Wraps the specified block in an operation and adds it to the receiver.
     * This method adds a single block to the receiver by first wrapping it in an operation object.
     * You should not attempt to get a reference to the newly created operation object or determine its type information.
     * @param Closure(): void $block The block to execute from the operation. The block takes no parameters and has no return value.
     */
    public function addOperationWithBlock(Closure $block): void
    {
        $this->addOperation(new BlockOperation($block));
    }

    /**
     * Cancels all queued and executing operations.
     *
     * This method calls the {@see Operation::cancel()} method on all operations currently in the queue.
     */
    public function cancelAllOperations(): void
    {
        foreach ($this->operations as $operation) {
            $operation->cancel();
        }
    }

    /**
     * Blocks the current thread until all the receiver's queued and executing operations finish executing.
     */
    public function waitUntilAllOperationsAreFinished(): void
    {
        foreach ($this->operations as $operation) {
            $operation->waitUntilFinished();
        }
    }
}
