<?php

namespace Sabatier\Foundation;

use Closure;

/**
 * An object that conveys ongoing progress to the user for a specified task.
 */
class Progress
{
    /** @var float The total number of tracked units of work for the current progress. */
    public float $totalUnitCount = 0.0;
    /** @var float The number of completed units of work for the current job. */
    public float $completedUnitCount = 0.0;

    /** @var string A localized description of tracked progress for the receiver. */
    public string $localizedDescription;

    /** @var string A more specific localized description of tracked progress for the receiver. */
    public string $localizedAdditionalDescription;

    /** @var bool A Boolean value that indicates whether the receiver is tracking work that you can cancel. */
    public bool $isCancellable = true;

    /** @var bool A Boolean value that Indicates whether the receiver is tracking canceled work. */
    public bool $isCancelled = false;

    /** @var Closure(): void|null The block to invoke when canceling progress. */
    public ?Closure $cancellationHandler = null;

    /** @var bool A Boolean value that indicates whether the receiver is tracking work that you can pause. */
    public bool $isPausable = false;

    /** @var bool A Boolean value that indicates whether the receiver is tracking paused work. */
    public bool $isPaused = false;

    /** @var Closure(): void|null The block to invoke when pausing progress. */
    public ?Closure $pausingHandler = null;

    /** @var bool A Boolean value that indicates whether the tracked progress is indeterminate. */
    public bool $isIndeterminate = false;

    /** @var float The fraction of the overall work that the progress object completes, including work from its suboperations. */
    public float $fractionCompleted = 0.0;

    /** @var bool A Boolean value that indicates the progress object is complete. */
    public bool $isFinished = false;

    /** @var Closure(): void|null The block to invoke when progress resumes. */
    public ?Closure $resumingHandler = null;

    /** @var string|null An object that represents the kind of progress for the progress object. */
    public ?string $kind = null;

    /** @var float|null A value that indicates the estimated amount of time remaining to complete the progress. */
    public ?float $estimatedTimeRemaining = null;
    /** @var int|null A value that represents the speed of data processing, in bytes per second. */
    public ?int $throughput = null;

    /** @var Dictionary<mixed> A dictionary of arbitrary values for the receiver. */
    public Dictionary $userInfo;

    /**
     * @param Progress|null $parent The containing Progress object, if any, to notify when reporting progress, or to consult when checking for cancellation.
     *
     * The only valid values are current() or nil.
     * @param Dictionary|null $userInfo The optional user information dictionary for the progress object.
     */
    public function __construct(?Progress $parent = null, ?Dictionary $userInfo = null)
    {
    }

    /**
     * Creates and returns a progress instance with the specified unit count that isn’t part of any existing progress tree.
     * @param int $totalUnitCount The total number of units of work to assign to the progress instance.
     * @return Progress A new progress instance with its containing progress object set to nil.
     */
    public static function discreteProgress(int $totalUnitCount): Progress
    {
        $progress = new Progress();
        $progress->totalUnitCount = $totalUnitCount;
        return $progress;
    }

    /**
     * The progress instance for the current thread, if any.
     * @return Progress|null
     */
    public static function current(): ?Progress
    {
        return null;
    }

    /**
     * Sets the progress object as the current object of the current thread, and assigns the amount of work for the next suboperation progress object to perform.
     * @param int $unitCount The number of units of work for the next progress object that initializes when you invoke {@see __construct()} in the current thread with this progress object as the containing progress object.
     *
     * The number represents the portion of work to perform in relation to the total number of units of work, which is the value of the progress object’s totalUnitCount property. The units of work for this parameter must be the same units of work in the progress object’s totalUnitCount property.
     */
    public function becomeCurrent(int $unitCount): void
    {
    }

    /**
     * Adds a process object as a suboperation of a progress tree.
     * @param Progress $child The progress instance to add to the progress tree.
     * @param int $unitCount The number of units of work for the new suboperation to complete.
     */
    public function addChild(Progress $child, int $unitCount): void
    {
    }

    /**
     * @template ReturnType of mixed
     * Retrieves the current thread’s progress object, executes the specified block, and increments the progress object by the specified units of work.
     * @param int $unitCount The number of units of work to increment for the current progress object. This number represents the portion of work that is complete in relation to the total number of units of work for the current thread’s progress object. The units of work for this parameter must be the same units of work as the current progress object’s {@see $totalUnitCount} property.
     * @param Closure(): ReturnType $work A block that wraps the work you specify to complete for incrementing the current progress.
     * @return ReturnType The return type and value of the block that you specify for the work parameter.
     */
    public function performAsCurrent(/** @noinspection PhpUnusedParameterInspection */ int $unitCount, Closure $work)
    {
        return null;
    }

    /**
     * Restores the previous progress object to become the current progress object on the thread.
     */
    public function resignCurrent(): void
    {
    }

    /**
     * Cancels progress tracking.
     *
     * This method invokes the block for {@see cancellationHandler}, if there is one, and ensures that any subsequent reads of the {@see isCancelled} property return true.
     *
     * If the receiver has suboperations, the system cancels their progress as well.
     */
    public function cancel(): void
    {
    }

    /**
     * Pauses progress tracking.
     *
     * This method invokes the block for {@see pausingHandler}, if there is one, and ensures that any subsequent reads of the {@see isPaused} property return true.
     *
     * If the receiver has suboperations, the system pauses their progress as well.
     */
    public function pause(): void
    {
    }

    /**
     * Resumes progress tracking.
     *
     * This method invokes the block for {@see resumingHandler}, if there is one, and ensures that any subsequent reads of the {@see isPaused} property return false.
     *
     * If the receiver has suboperations, the system resumes their progress as well.
     */
    public function resume(): void
    {
    }

    /**
     * Sets a value in the user info dictionary.
     *
     * Use this method to set a value in the {@see userInfo} dictionary, with appropriate KVO notification for properties with values that can depend on values in the user info dictionary, like {@see localizedDescription}.
     *
     * Supply a value of nil to remove an existing dictionary entry for a specified key.
     * @param mixed $objectOrNil The object to set for the specified key, or nil to remove an existing entry in the dictionary.
     * @param string $key The key for storing the specified object.
     */
    public function setUserInfoObject(mixed $objectOrNil, string $key): void
    {
        $this->userInfo[$key] = $objectOrNil;
    }
}
