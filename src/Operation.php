<?php

/**
 * Created by PhpStorm.
 * User: dante
 * Date: 09/06/20
 * Time: 13:14
 */

namespace Sabatier\Foundation;

use Closure;
use Fiber;
use Override;
use Throwable;

/**
 * An abstract class that represents the code and data associated with a single task.
 * @property-read bool $isCancelled A Boolean value indicating whether the operation has been cancelled.
 * @property-read bool $isExecuting A Boolean value indicating whether the operation is currently executing.
 * @property-read bool $isFinished A Boolean value indicating whether the operation has finished executing its task.
 * @property-read bool $isConcurrent A Boolean value indicating whether the operation executes its task asynchronously. Use the {@see isAsynchronous} property instead. The value of this property is true for operations that run asynchronously with respect to the current thread or false for operations that run synchronously on the current thread. The default value of this property is false.
 * @property-read bool $isAsynchronous A Boolean value indicating whether the operation executes its task asynchronously. The value of this property is true for operations that run asynchronously with respect to the current thread or false for operations that run synchronously on the current thread. The default value of this property is false.
 * @property-read bool $isReady A Boolean value indicating whether the operation can be performed now. The readiness of operations is determined by their dependencies on other operations and potentially by custom conditions that you define. The Operation class manages dependencies on other operations and reports the readiness of the receiver based on those dependencies. If you want to use custom conditions to define the readiness of your operation object, reimplement this property and return a value that accurately reflects the readiness of the receiver. If you do so, your custom implementation must get the default property value from super and incorporate that readiness value into the new value of the property. In your custom implementation, you must generate KVO notifications for the isReady key path whenever the ready state of your operation object changes.
 */
abstract class Operation extends ObjectClass
{
    protected bool $isCancelled = false;
    protected bool $isExecuting = false;
    protected bool $isFinished = false;
    protected bool $isConcurrent = false;
    protected bool $isAsynchronous = false;
    protected bool $isReady = true;
    /** @var string|null The name of the operation. */
    public ?string $name = null;
    /** @var OperationQueuePriority The execution priority of the operation in an operation queue. */
    public OperationQueuePriority $queuePriority = OperationQueuePriority::normal;
    /** @var Closure(): void|null The block to execute after the operation's main task is completed. */
    public ?Closure $completionBlock = null;
    /** @var ArrayClass<Operation> */
    public readonly ArrayClass $dependencies;
    /** @internal */
    public int $pid = NotFound;
    /** @internal */
    public OperationQueue $queue;

    public function __construct()
    {
        $this->dependencies = new ArrayClass();
    }

    public function __get(string $name)
    {
        return match ($name) {
            "isCancelled", "isExecuting", "isFinished", "isConcurrent", "isAsynchronous", "isReady" => $this->$name,
            default => $this->valueForUndefinedKey($name)
        };
    }

    public function __set(string $name, mixed $value): void
    {
        $this->willChangeValueForKey($name);
        $this->$name = match ($name) {
            "isCancelled" => (function () use ($value): bool {
                $this->dependencies->setValueForKey($this->isCancelled, "isCancelled");
                return $value;
            })(),
            "isExecuting", "isFinished", "isConcurrent", "isAsynchronous", "isReady" => $value,
            default => $this->valueForUndefinedKey($name)
        };
        $this->didChangeValueForKey($name);
    }

    /**
     * Begins the execution of the operation.
     */
    public function start(): void
    {
        if ($this->isCancelled || $this->isExecuting || $this->isFinished) {
            return;
        }
        try {
            $fiber = new Fiber(function (): void {
                Fiber::suspend();
                $this->queue->isCurrentQueue = true;
                $this->setValueForKey(true, "isExecuting");
                $this->main();
                $this->setValueForKey(false, "isExecuting");
                if ($completionBlock = $this->completionBlock) {
                    $completionBlock();
                }
                $this->setValueForKey(true, "isFinished");
                $this->queue->isCurrentQueue = false;
            });
            $fiber->start();
            if (!$fiber->isTerminated()) {
                $fiber->resume();
            }
        } catch (Throwable) {
        }
    }

    /**
     * Performs the receiver's non-concurrent task.
     *
     * The default implementation of this method does nothing. You should override this method to perform the desired task. In your implementation, do not invoke super.
     * If you are implementing a concurrent operation, you are not required to override this method but may do so if you plan to call it from your custom start() method.
     */
    public function main(): void
    {
    }

    /**
     * Advises the operation object that it should stop executing its task.
     *
     * This method does not force your operation code to stop. Instead, it updates the object's internal flags to reflect the change in state. If the operation has already finished executing, this method has no effect. Canceling an operation that is currently in an operation queue, but not yet executing, makes it possible to remove the operation from the queue sooner than usual.
     */
    public function cancel(): void
    {
        if ($this->isCancelled) {
            return;
        }
        $this->setValueForKey(true, "isCancelled");
    }

    /**
     * Makes the receiver dependent on the completion of the specified operation.
     *
     * The receiver is not considered ready to execute until all of its dependent operations have finished executing. If the receiver is already executing its task, adding dependencies has no practical effect. This method may change the {@see isReady} and dependencies properties of the receiver.
     * It is a programmer error to create any circular dependencies among a set of operations. Doing so can cause a deadlock among the operations and may freeze your program.
     * @param Operation $operation The operation on which the receiver should depend. The same dependency should not be added more than once to the receiver, and the results of doing so are undefined.
     */
    public function addDependency(Operation $operation): void
    {
        $this->setValueForKey(false, "isReady");
        $operation->observe("isFinished", KeyValueObservingOptions::new, function (Operation $operation, KeyValueObservedChange $change): void {
            if ($change->newValue) {
                $this->removeDependency($operation);
            }
        });
        $this->dependencies->append($operation);
    }

    /**
     * Removes the receiver's dependence on the specified operation.
     *
     * This method may change the {@see isReady} and dependencies properties of the receiver.
     * @param Operation $operation The dependent operation to be removed from the receiver.
     */
    public function removeDependency(Operation $operation): void
    {
        $this->dependencies->remove($operation);
        $this->setValueForKey($this->dependencies->isEmpty, "isReady");
    }

    /**
     * Blocks execution of the current thread until the operation object finishes its task.
     *
     * An operation object must never call this method on itself and should avoid calling it on any operations submitted to the same operation queue as itself. Doing so can cause the operation to deadlock. Instead, other parts of your app may call this method as needed to prevent other tasks from completing until the target operation object finishes. It is generally safe to call this method on an operation that is in a different operation queue, although it is still possible to create deadlocks if each operation waits on the other.
     * A typical use for this method would be to call it from the code that created the operation in the first place. After submitting the operation to a queue, you would call this method to wait until that operation finished executing.
     */
    public function waitUntilFinished(): void
    {
    }

    #[Override]
    public function description(): string
    {
        return sprintf("<%s %s>", self::class, $this->name ?? $this->hash());
    }
}
