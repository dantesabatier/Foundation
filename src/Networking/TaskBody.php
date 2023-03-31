<?php

namespace Sabatier\Foundation\Networking;

use Exception;
use Sabatier\Foundation\FileAttributeKey;
use Sabatier\Foundation\FileManager;
use Sabatier\Foundation\URL;

/** @internal */
readonly class TaskBody
{
    private function __construct(public TaskBodyRawValue $rawValue, public ?string $data = null, public ?URL $fileURL = null, public mixed $stream = null)
    {
    }

    public static function none(): TaskBody
    {
        return new TaskBody(TaskBodyRawValue::none);
    }

    public static function data(string $data): TaskBody
    {
        return new TaskBody(TaskBodyRawValue::data, $data);
    }

    public static function file(URL $fileURL): TaskBody
    {
        return new TaskBody(TaskBodyRawValue::file, fileURL: $fileURL);
    }

    public static function stream(mixed $stream): TaskBody
    {
        return new TaskBody(TaskBodyRawValue::stream, stream: $stream);
    }

    /**
     * @throws Exception
     */
    public function getBodyLength(): ?int
    {
        return match ($this->rawValue) {
            TaskBodyRawValue::none, TaskBodyRawValue::stream => 0,
            TaskBodyRawValue::data => strlen((string)$this->data),
            TaskBodyRawValue::file => FileManager::default()->attributesOfItem((string)$this->fileURL?->path)[FileAttributeKey::size]
        };
    }
}
