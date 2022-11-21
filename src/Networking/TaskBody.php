<?php

namespace Sabatier\Foundation\Networking;

use Exception;
use Sabatier\Foundation\FileAttributeKey;
use Sabatier\Foundation\FileManager;
use Sabatier\Foundation\URL;

/** @internal */
class TaskBody
{
    private function __construct(public readonly TaskBodyRawValue $rawValue, public ?string $data = null, public ?URL $fileURL = null)
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

    /**
     * @throws Exception
     */
    public function getBodyLength(): ?int
    {
        return match ($this->rawValue) {
            TaskBodyRawValue::none => 0,
            TaskBodyRawValue::data => strlen((string)$this->data),
            TaskBodyRawValue::file => FileManager::default()->attributesOfItem((string)$this->fileURL?->path)[FileAttributeKey::size]
        };
    }
}
