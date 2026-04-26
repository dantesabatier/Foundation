<?php

declare(strict_types=1);

namespace Sabatier\Foundation\Networking;

enum CompletionActionRawValue
{
    case completeTask;
    case failWithError;
    case redirectWithRequest;
}
