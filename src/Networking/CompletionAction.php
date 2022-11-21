<?php

namespace Sabatier\Foundation\Networking;

use const Sabatier\Foundation\URLErrorUnknown;

class CompletionAction
{
    private function __construct(public readonly CompletionActionRawValue $rawValue, public readonly ?URLRequest $newRequest = null, public readonly int $errorCode = URLErrorUnknown)
    {
    }

    public static function completeTask(): CompletionAction
    {
        return new CompletionAction(CompletionActionRawValue::completeTask);
    }

    public static function failWithError(int $errorCode): CompletionAction
    {
        return new CompletionAction(CompletionActionRawValue::failWithError, errorCode: $errorCode);
    }

    public static function redirectWithRequest(URLRequest $newRequest): CompletionAction
    {
        return new CompletionAction(CompletionActionRawValue::redirectWithRequest, $newRequest);
    }
}
