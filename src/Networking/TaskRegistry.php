<?php

declare(strict_types=1);

namespace Sabatier\Foundation\Networking;

use Closure;
use Sabatier\Foundation\Dictionary;
use Sabatier\Foundation\ObjectClass;
use function Sabatier\Foundation\fatal_error;

/** @internal */
final class TaskRegistry extends ObjectClass
{
    /** @var Dictionary<URLSessionTask> */
    private(set) Dictionary $allTask;
    /** @var Dictionary<TaskRegistryBehaviour> */
    private Dictionary $behaviours;
    /** @var Closure(): void|null */
    private ?Closure $tasksFinishedCallback = null;
    public bool $isEmpty {
        get => $this->allTask->isEmpty;
    }

    public function __construct()
    {
        $this->allTask = new Dictionary();
        $this->behaviours = new Dictionary();
    }

    public function add(URLSessionTask $task, TaskRegistryBehaviour $behaviour): void
    {
        $identifier = (string)$task->taskIdentifier;
        if ($this->behaviours[$identifier]) {
            if ($this->allTask[$identifier] === $task) {
                fatal_error("Trying to re-insert a task that's already in the registry.");
            } else {
                fatal_error("Trying to insert a task, but a different task with the same identifier is already in the registry.");
            }
        }
        $this->allTask[$identifier] = $task;
        $this->behaviours[$identifier] = $behaviour;
    }

    public function remove(URLSessionTask $task): void
    {
        if (!($key = $this->allTask->indexOf($task))) {
            fatal_error("Trying to remove task, but it's not in the registry.");
        }
        $this->allTask->removeValueForKey($key);
        if (!($key = $this->behaviours->indexOf($this->behaviour($task)))) {
            fatal_error("Trying to remove task's behaviour, but it's not in the registry.");
        }
        $this->behaviours->removeValueForKey($key);
        if (!($allTasksFinished = $this->tasksFinishedCallback)) {
            return;
        }
        if ($this->isEmpty) {
            $allTasksFinished();
        }
    }

    /**
     * @param Closure(): void $tasksCompletion
     */
    public function notify(Closure $tasksCompletion): void
    {
        $this->tasksFinishedCallback = $tasksCompletion;
    }

    public function behaviour(URLSessionTask $task): TaskRegistryBehaviour
    {
        if (!($behaviour = $this->behaviours[(string)$task->taskIdentifier])) {
            fatal_error();
        }
        return $behaviour;
    }
}
