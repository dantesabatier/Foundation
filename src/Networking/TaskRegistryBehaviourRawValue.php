<?php

declare(strict_types=1);

namespace Sabatier\Foundation\Networking;

enum TaskRegistryBehaviourRawValue
{
    case callDelegate;
    case dataCompletionHandler;
    case downloadCompletionHandler;
}
