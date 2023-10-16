<?php

namespace Sabatier\Foundation;

use Closure;
use JetBrains\PhpStorm\ExpectedValues;

/**
 * An object that conveys ongoing progress to the user for a specified task.
 * @property float $totalUnitCount The total number of tracked units of work for the current progress.
 * @property float $completedUnitCount The number of completed units of work for the current job.
 * @property-read bool $isCancelled A Boolean value that Indicates whether the receiver is tracking canceled work. By default, Progress is KVO-compliant for this property. It sends notifications on the same thread that updates the property. If the receiver has a canceled containing progress object, the receiver reports a canceled status.
 * @property-read bool $isPaused A Boolean value that indicates whether the receiver is tracking paused work. By default, Progress is KVO-compliant for this property. It sends notifications on the same thread that updates the property. If the receiver has a paused containing progress object, the receiver reports a paused status.
 * @property-read bool $isIndeterminate A Boolean value that indicates whether the tracked progress is indeterminate. Use isIndeterminate progress only when you’re unable to determine a reasonable value for either {@see $completedUnitCount} or {@see $totalUnitCount}. Progress is indeterminate when the value of the totalUnitCount or completedUnitCount is less than zero or if both values are zero. When progress is indeterminate, {@see $fractionCompleted} returns 0.0 and {@see $isFinished} returns false. By default, Progress is KVO-compliant for this property. It sends notifications on the same thread that updates the property.
 * @property-read float $fractionCompleted The fraction of the overall work that the progress object completes, including work from its suboperations.
 * @property-read bool $isFinished A Boolean value that indicates the progress object is complete. A progress object finishes when the {@see $completedUnitCount} equals or exceeds the {@see $totalUnitCount}. By default, Progress is KVO-compliant for this property. It sends notifications on the same thread that updates the property.
 * @property-read bool $isOld A Boolean value that indicates when the observed progress object invokes the publish method before you subscribe to it. The publish and subscribe mechanism is generally level-triggered, in that when you invoke {@see addSubscriber()}, the system invokes your block for every relevant published and unpublished progress object. Sometimes you need to implement edge-triggered behavior, in which you do something either exactly when new progress begins or not at all. In the example above, the Dock doesn’t animate file icons when this method returns true. There’s no reliable definition of before in this case, which involves multiple processes in a preemptively scheduled system. Don’t use this method for anything more important than best efforts at animating. It can be inaccurate due to processes coming and going from unpredictable user actions.
 * @psalm-type UnpublishingHandler = Closure(): void
 * @psalm-type PublishingHandler = Closure(Progress): ?UnpublishingHandler
 */
class Progress extends ObjectClass
{
    protected float $totalUnitCount = 0.0;
    protected float $completedUnitCount = 0.0;
    /** @var string A localized description of tracked progress for the receiver. */
    public string $localizedDescription = "";
    /** @var string A more specific localized description of tracked progress for the receiver. */
    public string $localizedAdditionalDescription = "";
    /** @var bool A Boolean value that indicates whether the receiver is tracking work that you can cancel. */
    public bool $isCancellable = false;
    protected bool $isCancelled = false;
    /** @var Closure(): void|null The block to invoke when canceling progress. */
    public ?Closure $cancellationHandler = null;
    /** @var bool A Boolean value that indicates whether the receiver is tracking work that you can pause. */
    public bool $isPausable = false;
    protected bool $isPaused = false;
    /** @var Closure(): void|null The block to invoke when pausing progress. */
    public ?Closure $pausingHandler = null;
    /** @var Closure(): void|null The block to invoke when progress resumes. */
    public ?Closure $resumingHandler = null;
    /** @var string|null An object that represents the kind of progress for the progress object. */
    #[ExpectedValues(valuesFromClass: ProgressKind::class)]
    public ?string $kind = null;
    /** @var float|null A value that indicates the estimated amount of time remaining to complete the progress. */
    public ?float $estimatedTimeRemaining = null;
    /** @var int|null A value that represents the speed of data processing, in bytes per second. */
    public ?int $throughput = null;
    /** @var Dictionary<mixed> A dictionary of arbitrary values for the receiver. */
    public readonly Dictionary $userInfo;
    /** @var string|null The kind of file operation for the progress object. */
    #[ExpectedValues(valuesFromClass: ProgressKindFile::class)]
    public ?string $fileOperationKind = null;
    /** @var URL|null A URL that represents the file for the current progress object. */
    public ?URL $fileURL = null;
    /** @var int|null The total number of files for a file progress object. */
    public ?int $fileTotalCount = null;
    /** @var int|null The number of completed files for a file progress object. */
    public ?int $fileCompletedCount = null;
    protected bool $isOld = false;
    private ?Progress $parent;
    /** @var Set<Progress> */
    private Set $children;
    private ProgressFraction $fraction;
    private ProgressFraction $childFraction;
    private float $portionOfParent = 0.0;

    /**
     * @param Progress|null $parent The containing Progress object, if any, to notify when reporting progress, or to consult when checking for cancellation.
     *
     * The only valid values are current() or nil.
     * @param Dictionary|null $userInfo The optional user information dictionary for the progress object.
     */
    public function __construct(?Progress $parent = null, ?Dictionary $userInfo = null)
    {
        $this->userInfo = $userInfo ?? new Dictionary();
        $this->children = new Set();
        $this->fraction = new ProgressFraction();
        $this->childFraction = new ProgressFraction();
        $this->childFraction->total = 1.0;
        $parent?->addChild($this, $this->totalUnitCount);
    }

    public function __get(string $name)
    {
        return match ($name) {
            "totalUnitCount", "completedUnitCount", "isCancelled", "isPaused", "isOld" => $this->$name,
            "isIndeterminate" => !($this->totalUnitCount && $this->completedUnitCount),
            "fractionCompleted" => $this->totalUnitCount && $this->completedUnitCount ? $this->completedUnitCount / $this->totalUnitCount : 0.0,
            "isFinished" => $this->totalUnitCount && $this->completedUnitCount && $this->completedUnitCount >= $this->totalUnitCount,
            default => $this->valueForUndefinedKey($name)
        };
    }

    public function __set(string $name, mixed $value): void
    {
        if ($name == "totalUnitCount" || $name == "completedUnitCount") {
            if ($value !== $this->$name) {
                $this->willChangeValueForKey("isFinished");
                $this->willChangeValueForKey("isIndeterminate");
                $this->willChangeValueForKey("fractionCompleted");
                $this->willChangeValueForKey($name);
                $this->$name = $value;
                $this->didChangeValueForKey($name);
                $this->didChangeValueForKey("isFinished");
                $this->didChangeValueForKey("isIndeterminate");
                $this->didChangeValueForKey("fractionCompleted");
            }
        } else {
            $this->setValueForUndefinedKey($value, $name);
        }
    }

    /**
     * Creates and returns a progress instance with the specified unit count that isn’t part of any existing progress tree.
     * @param float $totalUnitCount The total number of units of work to assign to the progress instance.
     * @return Progress A new progress instance with its containing progress object set to nil.
     */
    public static function discreteProgress(float $totalUnitCount): Progress
    {
        $progress = new Progress();
        $progress->totalUnitCount = $totalUnitCount;
        return $progress;
    }

    /**
     * Creates a progress instance for the specified progress object with a unit count that’s a portion of the containing object’s total unit count.
     * @param float $totalUnitCount The total number of units of work to assign to the progress instance.
     * @param Progress|null $parent The containing progress object for the created Progress object.
     * @param float $pendingUnitCount The unit count for the progress object.
     * @return Progress
     */
    public static function progress(float $totalUnitCount, ?Progress $parent = null, float $pendingUnitCount = 0.0): Progress
    {
        $progress = new Progress();
        $progress->totalUnitCount = $totalUnitCount;
        $parent?->addChild($progress, $pendingUnitCount);
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
     * @param float $unitCount The number of units of work for the next progress object that initializes when you invoke {@see __construct()} in the current thread with this progress object as the containing progress object.
     *
     * The number represents the portion of work to perform in relation to the total number of units of work, which is the value of the progress object’s totalUnitCount property. The units of work for this parameter must be the same units of work in the progress object’s totalUnitCount property.
     */
    public function becomeCurrent(float $unitCount): void
    {
    }

    /**
     * Adds a process object as a suboperation of a progress tree.
     * @param Progress $child The progress instance to add to the progress tree.
     * @param float $unitCount The number of units of work for the new suboperation to complete.
     */
    public function addChild(Progress $child, float $unitCount): void
    {
        $child->parent === null ?: fatal_error("The Progress was already the child of another Progress");
        $child->setParent($this, $unitCount);
        $this->children->append($child);
        if ($this->isCancelled) {
            $child->cancel();
        }
        if ($this->isPaused) {
            $child->pause();
        }
    }

    private function setParent(Progress $parent, float $portion): void
    {
        $this->parent = $parent;
        $this->portionOfParent = $portion;
    }

    /**
     * @template ReturnType of mixed
     * Retrieves the current thread’s progress object, executes the specified block, and increments the progress object by the specified units of work.
     * @param float $unitCount The number of units of work to increment for the current progress object. This number represents the portion of work that is complete in relation to the total number of units of work for the current thread’s progress object. The units of work for this parameter must be the same units of work as the current progress object’s {@see $totalUnitCount} property.
     * @param Closure(): ReturnType $work A block that wraps the work you specify to complete for incrementing the current progress.
     * @return ReturnType The return type and value of the block that you specify for the work parameter.
     */
    public function performAsCurrent(/** @noinspection PhpUnusedParameterInspection */ float $unitCount, Closure $work)
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
     * This method invokes the block for {@see $cancellationHandler}, if there is one, and ensures that any subsequent reads of the {@see $isCancelled} property return true.
     *
     * If the receiver has subpopulations, the system cancels their progress as well.
     */
    public function cancel(): void
    {
        if ($this->isCancellable && !$this->isCancelled) {
            $this->isCancelled = true;
            $cancellationHandler = $this->cancellationHandler;
            if ($cancellationHandler) {
                $cancellationHandler();
            }
            foreach ($this->children as $child) {
                $child->cancel();
            }
        }
    }

    /**
     * Pauses progress tracking.
     *
     * This method invokes the block for {@see $pausingHandler}, if there is one, and ensures that any subsequent reads of the {@see $isPaused} property return true.
     *
     * If the receiver has suboperations, the system pauses their progress as well.
     */
    public function pause(): void
    {
        if ($this->isPausable && !$this->isPaused) {
            $this->isPaused = true;
            $pausingHandler = $this->pausingHandler;
            if ($pausingHandler) {
                $pausingHandler();
            }
            foreach ($this->children as $child) {
                $child->pause();
            }
        }
    }

    /**
     * Resumes progress tracking.
     *
     * This method invokes the block for {@see $resumingHandler}, if there is one, and ensures that any subsequent reads of the {@see $isPaused} property return false.
     *
     * If the receiver has suboperations, the system resumes their progress as well.
     */
    public function resume(): void
    {
        if ($this->isPausable && $this->isPaused) {
            $this->isPaused = false;
            $resumingHandler = $this->resumingHandler;
            if ($resumingHandler) {
                $resumingHandler();
            }
            foreach ($this->children as $child) {
                $child->resume();
            }
        }
    }

    /**
     * Sets a value in the user info dictionary.
     *
     * Use this method to set a value in the {@see $userInfo} dictionary, with appropriate KVO notification for properties with values that can depend on values in the user info dictionary, like {@see $localizedDescription}.
     *
     * Supply a value of nil to remove an existing dictionary entry for a specified key.
     * @param mixed $objectOrNil The object to set for the specified key, or nil to remove an existing entry in the dictionary.
     * @param string $key The key for storing the specified object.
     */
    public function setUserInfoObject(mixed $objectOrNil, string $key): void
    {
        $this->userInfo->setValueForKey($objectOrNil, $key);
    }

    /**
     * Publishes the progress object for other processes to observe it.
     *
     * Entries in the user info dictionary determine whether another process can discover the progress object to observe it, and how it does that. For example, a {@see ProgressUserInfoKey::fileURLKey} entry makes a progress object discoverable by corresponding invokers of {@see addSubscriber()}. The system constrains access to the published progress URL with your app sandbox. If you can’t see the file due to the app’s sandbox restrictions, you can’t observe the progress on it.
     *
     * When you make a progress object observable by other processes, you must ensure that at least {@see $localizedDescription}, {@see $isIndeterminate}, and {@see $fractionCompleted} always work when you send proxies of your progress object in other processes. You make {@see $isIndeterminate} and {@see $fractionCompleted} work by accurately setting the total and completed unit counts of the progress. You make {@see $localizedDescription} work by setting the value of the kind property to something valid, like file, and then fulfilling the requirements for that kind of progress.
     *
     * You can instead set the value of localizedDescription directly, but that’s not perfectly reliable because other processes might be using a different localization than yours.
     *
     * You can publish an instance of {@see Progress} one time only.
     */
    public function publish(): void
    {
    }

    /**
     * Removes a progress object from publication, making it unobservable by other processes.
     */
    public function unpublish(): void
    {
    }

    /**
     * Registers a file URL to hear about the progress of a file operation.
     *
     * The system invokes the passed-in block when a progress object calls {@see publish()} with a {@see ProgressUserInfoKey::fileURLKey} user info dictionary entry that’s a URL that is the same as this method’s URL, or that is an item that the URL directly contains. The progress object that passes to your block is a proxy of the published progress object. The passed-in block may return another block. If it does, the system invokes the returned block when the observed progress object invokes {@see unpublish()}, the publishing process terminates, or you invoke {@see removeSubscriber()}. The system invokes the blocks you provide on the main thread.
     * @param URL $url The URL of the file to observe.
     * @param PublishingHandler $publishingHandler A closure that the system invokes when a progress object that represents a file operation matching the specified URL calls publish().
     * @return mixed A proxy of the progress object to observe.
     */
    public static function addSubscriber(/** @noinspection PhpUnusedParameterInspection */ URL $url, Closure $publishingHandler): mixed
    {
        return null;
    }

    /**
     * Removes a proxy progress object that the add subscriber method returns.
     *
     * If the block for {@see addSubscriber()} returns a closure, the system invokes that closure on the main thread when you invoke removeSubscriber().
     * @param mixed $subscriber The proxy of the progress object to observe.
     */
    public static function removeSubscriber(mixed $subscriber): void
    {
    }
}
