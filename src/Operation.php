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
 */
abstract class Operation extends ObjectClass
{
    /** @var bool A Boolean value indicating whether the operation has been canceled. */
    private(set) bool $isCancelled = false {
        set {
            $this->willChangeValueForKey(__PROPERTY__, changedValue: $this->isCancelled);
            $this->isCancelled = $value;
            $this->didChangeValueForKey(__PROPERTY__, changedValue: $this->isCancelled);
            $this->dependencies->setValueForKey($value, __PROPERTY__);
        }
    }
    /** @var bool A Boolean value indicating whether the operation is currently executing. */
    private(set) bool $isExecuting = false {
        set {
            $this->willChangeValueForKey(__PROPERTY__, changedValue: $this->isExecuting);
            $this->isExecuting = $value;
            $this->didChangeValueForKey(__PROPERTY__, changedValue: $this->isExecuting);
        }
    }
    /** @var bool A Boolean value indicating whether the operation has finished executing its task. */
    private(set) bool $isFinished = false {
        set {
            $this->willChangeValueForKey(__PROPERTY__, changedValue: $this->isFinished);
            $this->isFinished = $value;
            $this->didChangeValueForKey(__PROPERTY__, changedValue: $this->isFinished);
        }
    }
    /** @var bool A Boolean value indicating whether the operation executes its task asynchronously. Use the {@see isAsynchronous} property instead. The value of this property is true for operations that run asynchronously with respect to the current thread or false for operations that run synchronously on the current thread. The default value of this property is false. */
    private(set) bool $isConcurrent = false;
    /** @var bool A Boolean value indicating whether the operation executes its task asynchronously. The value of this property is true for operations that run asynchronously with respect to the current thread or false for operations that run synchronously on the current thread. The default value of this property is false. */
    private(set) bool $isAsynchronous = false;
    /** @var bool A Boolean value indicating whether the operation can be performed now. The readiness of operations is determined by their dependencies on other operations and potentially by custom conditions that you define. The Operation class manages dependencies on other operations and reports the readiness of the receiver based on those dependencies. If you want to use custom conditions to define the readiness of your operation object, reimplement this property and return a value that accurately reflects the readiness of the receiver. If you do so, your custom implementation must get the default property value from the parent and incorporate that readiness value into the new value of the property. In your custom implementation, you must generate KVO notifications for the isReady key path whenever the ready state of your operation object changes. */
    private(set) bool $isReady = true {
        set {
            $this->willChangeValueForKey(__PROPERTY__, changedValue: $this->isReady);
            $this->isReady = $value;
            $this->didChangeValueForKey(__PROPERTY__, changedValue: $this->isReady);
        }
    }
    /** @var string|null The name of the operation. */
    public ?string $name = null;
    /** @var OperationQueuePriority The execution priority of the operation in an operation queue. */
    public OperationQueuePriority $queuePriority = OperationQueuePriority::normal;
    /** @var Closure(): void|null The block to execute after the operation's main task is completed. */
    public ?Closure $completionBlock = null;
    /** @var ArrayClass<Operation> */
    private(set) ArrayClass $dependencies {
        get => $this->dependencies ??= new ArrayClass();
    }
    /** @internal */
    public OperationQueue $queue;
    /** @internal */
    private(set) ?Fiber $fiber = null;
    #[Override]
    public string $description {
        get => sprintf("<%s %s>", $this->class, $this->name ?? $this->hash);
    }

    /**
     * Begins the execution of the operation.
     */
    public function start(): void
    {
        if ($this->isCancelled || $this->isExecuting || $this->isFinished) {
            return;
        }
        $this->fiber = new Fiber(function (): void {
            $this->queue->isCurrentQueue = true;
            $this->isExecuting = true;
            $this->main();
            $this->isExecuting = false;
            if ($completionBlock = $this->completionBlock) {
                $completionBlock();
            }
            $this->isFinished = true;
            $this->queue->isCurrentQueue = false;
        });
        $this->fiber->start();
    }
    
    /**
     * Performs the receiver's non-concurrent task.
     *
     * The default implementation of this method does nothing. You should override this method to perform the desired task. In your implementation, do not invoke the parent.
     * If you are implementing a concurrent operation, you are not required to override this method but may do so if you plan to call it from your custom {@see start()} method.
     */
    public function main(): void
    {
    }

    /**
     * Advises the operation object that it should stop executing its task.
     *
     * This method does not force your operation code to stop. Instead, it updates the object's internal flags to reflect the change in state. If the operation has already finished executing, this method has no effect. Canceling an operation currently in an operation queue, but not yet executing, makes it possible to remove the operation from the queue sooner than usual.
     */
    public function cancel(): void
    {
        if ($this->isCancelled) {
            return;
        }
        $this->isCancelled = true;
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
        $this->isReady = false;
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
        $this->isReady = $this->dependencies->isEmpty;
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
}
