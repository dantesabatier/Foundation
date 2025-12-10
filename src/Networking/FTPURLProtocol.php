<?php

namespace Sabatier\Foundation\Networking;

use Exception;
use Override;
use Sabatier\Foundation\Dictionary;
use Sabatier\Foundation\Error;
use function Sabatier\Foundation\fatal_error;
use function Sabatier\Foundation\localized_string;
use const Sabatier\Foundation\LocalizedDescriptionKey;
use const Sabatier\Foundation\URLErrorDomain;
use const Sabatier\Foundation\URLErrorUnknown;

/** @internal */
class FTPURLProtocol extends NativeProtocol
{
    #[Override]
    public static function canInit(URLRequest $request): bool
    {
        // TODO: Implement sftp and ftps
        return $request->url->scheme === "ftp";
    }

    #[Override]
    public function didReceiveHeaderData(string $data, int $contentLength): EasyHandleAction
    {
        if ($this->internalState->rawValue !== InternalStateRawValue::transferInProgress) {
            fatal_error("Received header data, but no transfer in progress.");
        }
        $this->task->countOfBytesReceived = $contentLength ?: URLSessionTransferSizeUnknown;
        try {
            /** @var TransferState $ts */
            $ts = $this->internalState->transferState;
            $newTS = $ts->byAppendingFTP($data, $contentLength);
            $this->internalState = InternalState::transferInProgress($newTS);
            $didCompleteHeader = !$ts->isHeaderComplete() && $newTS->isHeaderComplete();
            if ($didCompleteHeader) {
                $this->didReceiveResponse();
            }
            return EasyHandleAction::proceed;
        } catch (Exception) {
            return EasyHandleAction::abort;
        }
    }

    #[Override]
    public function configureEasyHandle(URLRequest $request, TaskBody $body): void
    {
        $easyHandle = $this->easyHandle;
        $easyHandle->setVerboseModeOn(self::enableLibcurlDebugOutput());
        $easyHandle->setSkipAllSignalHandling(true);
        $easyHandle->setURL($request->url);
        $easyHandle->setPreferredReceiveBufferSize(PHP_INT_MAX);
        try {
            switch ($body->rawValue) {
                case TaskBodyRawValue::none:
                    $easyHandle->setRequestBodyLength(0);
                    break;
                case TaskBodyRawValue::data:
                case TaskBodyRawValue::file:
                case TaskBodyRawValue::stream:
                    if ($length = $body->getBodyLength()) {
                        $easyHandle->setRequestBodyLength($length);
                        $this->task->countOfBytesExpectedToSend = $length;
                    } else {
                        $easyHandle->setRequestBodyLength(URLResponseUnknownLength);
                    }
                    break;
            }
        } catch (Exception) {
            $this->internalState = InternalState::transferFailed();
            $error = new Error(URLErrorDomain, URLErrorUnknown, new Dictionary([LocalizedDescriptionKey => localized_string("File system error")]));
            $this->failWithError($error, $request);
            return;
        }
        $easyHandle->setAutomaticBodyDecompression(true);
    }

    public function didReceiveResponse(): void
    {
        if ($this->internalState->rawValue !== InternalStateRawValue::transferInProgress) {
            fatal_error("Received header data, but no transfer in progress.");
        }
        if (!($response = $this->internalState->transferState?->response)) {
            fatal_error("Header complete, but not URL response.");
        }
        $session = $this->task->session;
        $behaviour = $session->behaviour($this->task);
        switch ($behaviour->rawValue) {
            case TaskBehaviourRawValue::taskDelegate:
                $this->client?->urlProtocolDidReceiveCacheStoragePolicy($this, $response, URLCacheStoragePolicy::notAllowed);
                break;
            case TaskBehaviourRawValue::noDelegate:
            case TaskBehaviourRawValue::downloadCompletionHandler:
            case TaskBehaviourRawValue::dataCompletionHandler:
                break;
        }
    }
}
