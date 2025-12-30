<?php

namespace Sabatier\Foundation\Networking;

use Closure;

/** 
 * @internal
 * @psalm-import-type DataCompletionHandler from URLSession
 * @psalm-import-type DownloadCompletionHandler from URLSession
 */
final readonly class TaskBehaviour
{
    /**
     * @param TaskBehaviourRawValue $rawValue 
     * @param DataCompletionHandler|null $dataCompletionHandler 
     * @param DownloadCompletionHandler|null $downloadCompletionHandler 
     * @param URLSessionDelegate|null $taskDelegate 
     */
    private function __construct(public TaskBehaviourRawValue $rawValue, public ?Closure $dataCompletionHandler = null, public ?Closure $downloadCompletionHandler = null, public ?URLSessionDelegate $taskDelegate = null)
    {
    }

    public static function noDelegate(): TaskBehaviour
    {
        return new TaskBehaviour(TaskBehaviourRawValue::noDelegate);
    }

    public static function taskDelegate(URLSessionDelegate $delegate): TaskBehaviour
    {
        return new TaskBehaviour(TaskBehaviourRawValue::taskDelegate, taskDelegate: $delegate);
    }

    /**
     * @param DataCompletionHandler $dataCompletionHandler
     * @return TaskBehaviour
     */
    public static function dataCompletionHandler(Closure $dataCompletionHandler): TaskBehaviour
    {
        return new TaskBehaviour(TaskBehaviourRawValue::dataCompletionHandler, dataCompletionHandler: $dataCompletionHandler);
    }

    /**
     * @param DownloadCompletionHandler $downloadCompletionHandler
     * @return TaskBehaviour
     */
    public static function downloadCompletionHandler(Closure $downloadCompletionHandler): TaskBehaviour
    {
        return new TaskBehaviour(TaskBehaviourRawValue::downloadCompletionHandler, downloadCompletionHandler: $downloadCompletionHandler);
    }
}
