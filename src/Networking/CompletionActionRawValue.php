<?php

namespace Sabatier\Foundation\Networking;

enum CompletionActionRawValue
{
    case completeTask;
    case failWithError;
    case redirectWithRequest;
}