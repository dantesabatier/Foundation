<?php

declare(strict_types=1);

namespace Sabatier\Foundation\Networking;

use Sabatier\Foundation\Error;

/** @internal */
interface EasyHandleDelegate
{
    public function didReceiveData(string $data): EasyHandleAction;

    public function didReceiveHeaderData(string $data, int $contentLength): EasyHandleAction;

    public function fill(mixed $buffer, int $length): EasyHandleWriteBufferResult;

    public function transferCompleted(?Error $error): void;

    public function updateProgressMeter(EasyHandleProgress $progress): void;
}
