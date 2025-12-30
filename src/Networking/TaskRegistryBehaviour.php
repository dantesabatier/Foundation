<?php

namespace Sabatier\Foundation\Networking;

use Closure;

/** 
 * @internal
 * @psalm-import-type DataCompletionHandler from URLSession
 * @psalm-import-type DownloadCompletionHandler from URLSession
 */
final readonly class TaskRegistryBehaviour
{
    /**
     * @param TaskRegistryBehaviourRawValue $rawValue 
     * @param DataCompletionHandler|null $dataCompletionHandler 
     * @param DownloadCompletionHandler|null $downloadCompletionHandler 
     * @param URLSessionDelegate|null $taskDelegate 
     */
    private function __construct(public TaskRegistryBehaviourRawValue $rawValue, public ?Closure $dataCompletionHandler = null, public ?Closure $downloadCompletionHandler = null, public ?URLSessionDelegate $taskDelegate = null)
    {
    }

    public static function callDelegate(?URLSessionDelegate $delegate = null): TaskRegistryBehaviour
    {
        return new TaskRegistryBehaviour(TaskRegistryBehaviourRawValue::callDelegate, taskDelegate: $delegate);
    }

    /**
     * @param DataCompletionHandler $dataCompletionHandler
     * @return TaskRegistryBehaviour
     */
    public static function dataCompletionHandler(Closure $dataCompletionHandler): TaskRegistryBehaviour
    {
        return new TaskRegistryBehaviour(TaskRegistryBehaviourRawValue::dataCompletionHandler, $dataCompletionHandler);
    }

    /**
     * @param DownloadCompletionHandler $downloadCompletionHandler
     * @return TaskRegistryBehaviour
     */
    public static function downloadCompletionHandler(Closure $downloadCompletionHandler): TaskRegistryBehaviour
    {
        return new TaskRegistryBehaviour(TaskRegistryBehaviourRawValue::downloadCompletionHandler, downloadCompletionHandler: $downloadCompletionHandler);
    }
}
