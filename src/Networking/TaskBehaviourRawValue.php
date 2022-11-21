<?php

namespace Sabatier\Foundation\Networking;

enum TaskBehaviourRawValue
{
    case noDelegate;
    case taskDelegate;
    case dataCompletionHandler;
    case downloadCompletionHandler;
}
