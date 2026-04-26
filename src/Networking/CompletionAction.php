<?php

declare(strict_types=1);

namespace Sabatier\Foundation\Networking;

use const Sabatier\Foundation\URLErrorUnknown;

final readonly class CompletionAction
{
    private function __construct(public CompletionActionRawValue $rawValue, public ?URLRequest $newRequest = null, public int $errorCode = URLErrorUnknown)
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
