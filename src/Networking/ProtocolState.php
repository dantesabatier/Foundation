<?php

namespace Sabatier\Foundation\Networking;

/** @internal */
class ProtocolState
{
    private function __construct(public readonly ProtocolStateRawValue $rawValue, public readonly ?Bag $bag = null, public readonly ?URLProtocol $protocol = null)
    {
    }

    public static function toBeCreated(): ProtocolState
    {
        return new ProtocolState(ProtocolStateRawValue::toBeCreated);
    }

    public static function awaitingCacheReply(Bag $bag): ProtocolState
    {
        return new ProtocolState(ProtocolStateRawValue::awaitingCacheReply, $bag);
    }

    public static function existing(?URLProtocol $protocol): ProtocolState
    {
        return new ProtocolState(ProtocolStateRawValue::existing, protocol: $protocol);
    }

    public static function invalidated(): ProtocolState
    {
        return new ProtocolState(ProtocolStateRawValue::invalidated);
    }
}
