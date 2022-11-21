<?php

namespace Sabatier\Foundation\Networking;

/** @internal */
enum ProtocolStateRawValue
{
    case toBeCreated;
    case awaitingCacheReply;
    case existing;
    case invalidated;
}
