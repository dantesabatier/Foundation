<?php

declare(strict_types=1);

namespace Sabatier\Foundation\Networking;

/** @internal */
enum InternalStateRawValue
{
    case initial;
    case fulfillingFromCache;
    case transferReady;
    case transferInProgress;
    case transferCompleted;
    case transferFailed;
    case waitingForRedirectCompletionHandler;
    case waitingForResponseCompletionHandler;
    case taskCompleted;
}
