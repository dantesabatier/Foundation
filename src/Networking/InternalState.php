<?php

declare(strict_types=1);

namespace Sabatier\Foundation\Networking;

/** @internal */
final readonly class InternalState
{
    private function __construct(public InternalStateRawValue $rawValue, public ?CachedURLResponse $cachedURLResponse = null, public ?TransferState $transferState = null, public ?URLResponse $response = null, public ?DataDrain $bodyDataDrain = null)
    {
    }

    public static function initial(): InternalState
    {
        return new InternalState(InternalStateRawValue::initial);
    }

    public static function fulfillingFromCache(CachedURLResponse $cachedURLResponse): InternalState
    {
        return new InternalState(InternalStateRawValue::fulfillingFromCache, $cachedURLResponse);
    }

    public static function transferReady(TransferState $transferState): InternalState
    {
        return new InternalState(InternalStateRawValue::transferReady, transferState: $transferState);
    }

    public static function transferInProgress(TransferState $transferState): InternalState
    {
        return new InternalState(InternalStateRawValue::transferInProgress, transferState: $transferState);
    }

    public static function transferCompleted(URLResponse $response, DataDrain $dataDrain): InternalState
    {
        return new InternalState(InternalStateRawValue::transferCompleted, response: $response, bodyDataDrain: $dataDrain);
    }

    public static function transferFailed(): InternalState
    {
        return new InternalState(InternalStateRawValue::transferFailed);
    }

    public static function waitingForRedirectCompletionHandler(URLResponse $response, DataDrain $dataDrain): InternalState
    {
        return new InternalState(InternalStateRawValue::waitingForRedirectCompletionHandler, response: $response, bodyDataDrain: $dataDrain);
    }

    public static function waitingForResponseCompletionHandler(TransferState $transferState): InternalState
    {
        return new InternalState(InternalStateRawValue::waitingForResponseCompletionHandler, transferState: $transferState);
    }

    public static function taskCompleted(): InternalState
    {
        return new InternalState(InternalStateRawValue::taskCompleted);
    }

    public function isEasyHandleAddedToMultiHandle(): bool
    {
        return match ($this->rawValue) {
            InternalStateRawValue::transferInProgress, InternalStateRawValue::waitingForResponseCompletionHandler => true,
            default => false
        };
    }

    public function isEasyHandlePaused(): bool
    {
        return match ($this->rawValue) {
            InternalStateRawValue::waitingForResponseCompletionHandler => true,
            default => false
        };
    }
}
