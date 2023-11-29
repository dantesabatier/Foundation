<?php

namespace Sabatier\Foundation\Networking;

use Closure;
use Sabatier\Foundation\ArrayClass;
use Sabatier\Foundation\Dictionary;
use Sabatier\Foundation\ObjectClass;
use function Sabatier\Foundation\fatal_error;

/**
 * @internal
 * @property-read bool $isEmpty
 * @property-read ArrayClass<URLSessionTask> $allTask
 */
class TaskRegistry extends ObjectClass
{
    /** @var Dictionary<URLSessionTask> */
    private Dictionary $tasks;
    /** @var Dictionary<TaskRegistryBehaviour> */
    private Dictionary $behaviours;
    /** @var Closure(): void|null */
    private ?Closure $tasksFinishedCallback = null;

    public function __construct()
    {
        $this->tasks = new Dictionary();
        $this->behaviours = new Dictionary();
    }

    public function __get(string $name)
    {
        return match ($name) {
            "isEmpty" => $this->tasks->isEmpty,
            "allTask" => $this->tasks,
            default => $this->valueForUndefinedKey($name)
        };
    }

    public function add(URLSessionTask $task, TaskRegistryBehaviour $behaviour): void
    {
        $identifier = (string)$task->taskIdentifier;
        if ($this->behaviours[$identifier]) {
            if ($this->tasks[$identifier] === $task) {
                fatal_error("Trying to re-insert a task that's already in the registry.");
            } else {
                fatal_error("Trying to insert a task, but a different task with the same identifier is already in the registry.");
            }
        }
        $this->tasks[$identifier] = $task;
        $this->behaviours[$identifier] = $behaviour;
    }

    public function remove(URLSessionTask $task): void
    {
        if (!($key = $this->tasks->indexOf($task))) {
            fatal_error("Trying to remove task, but it's not in the registry.");
        }
        $this->tasks->removeValueForKey($key);
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
