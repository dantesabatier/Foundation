<?php

declare(strict_types=1);

namespace Sabatier\Foundation\Networking;

enum TaskBehaviourRawValue
{
    case noDelegate;
    case taskDelegate;
    case dataCompletionHandler;
    case downloadCompletionHandler;
}
