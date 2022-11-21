<?php

namespace Sabatier\Foundation\Networking;

enum TaskRegistryBehaviourRawValue
{
    case callDelegate;
    case dataCompletionHandler;
    case downloadCompletionHandler;
}
