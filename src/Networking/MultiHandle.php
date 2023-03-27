<?php

namespace Sabatier\Foundation\Networking;

use CurlHandle;
use CurlMultiHandle;
use Exception;
use Sabatier\Foundation\ArrayClass;
use Sabatier\Foundation\Dictionary;
use Sabatier\Foundation\Error;
use function Sabatier\Foundation\fatal_error;
use const Sabatier\Foundation\LocalizedFailureReasonErrorKey;
use const Sabatier\Foundation\URLErrorDomain;

/** @internal */
final readonly class MultiHandle
{
    private CurlMultiHandle $rawHandle;
    /** @var ArrayClass<EasyHandle> */
    private ArrayClass $easyHandles;

    public function __construct(URLSessionConfiguration $configuration)
    {
        $this->rawHandle = curl_multi_init();
        $this->easyHandles = new ArrayClass();
        $this->setupCallbacks();
        $this->configure($configuration);
    }

    public function __destruct()
    {
        foreach ($this->easyHandles as $easyHandle) {
            if ($easyHandle->rawHandle instanceof CurlHandle) {
                curl_multi_remove_handle($this->rawHandle, $easyHandle->rawHandle);
            }
        }
        curl_multi_close($this->rawHandle);
    }

    private function setupCallbacks(): void
    {
    }

    private function configure(URLSessionConfiguration $configuration): void
    {
        curl_multi_setopt($this->rawHandle, CURLMOPT_MAX_HOST_CONNECTIONS, $configuration->httpMaximumConnectionsPerHost);
        curl_multi_setopt($this->rawHandle, CURLMOPT_PIPELINING, $configuration->httpShouldUsePipelining ? 3 : 2);
    }

    /**
     * @throws Exception
     */
    public function add(EasyHandle $handle): void
    {
        if (!$handle->rawHandle instanceof CurlHandle) {
            $handle->connect();
            return;
        }
        $needsTimeout = $this->easyHandles->isEmpty();
        $this->easyHandles->append($handle);
        curl_multi_add_handle($this->rawHandle, $handle->rawHandle);
        if ($needsTimeout) {
            $this->timeoutTimerFired();
        }
    }

    public function remove(EasyHandle $handle): void
    {
        if (!$handle->rawHandle instanceof CurlHandle) {
            $handle->disconnect();
            return;
        }
        $this->easyHandles->remove($handle);
        curl_multi_remove_handle($this->rawHandle, $handle->rawHandle);
    }

    /**
     * @throws Exception
     */
    private function timeoutTimerFired(): void
    {
        do {
            curl_multi_exec($this->rawHandle, $running);
        } while ($running);
        $this->readMessages();
    }

    /**
     * @throws Exception
     */
    public function readMessages(): void
    {
        do {
            curl_multi_exec($this->rawHandle, $running);
            curl_multi_select($this->rawHandle);
            $info = curl_multi_info_read($this->rawHandle);
            if (!($handle = $info["handle"])) {
                break;
            }
            $code = $info["result"];
            $this->completedTransfer($handle, $code);
        } while ($running);
    }

    /**
     * @throws Exception
     */
    public function completedTransfer(CurlHandle $handle, int $easyCode): void
    {
        if (!($easyHandle = $this->easyHandles->first(fn(EasyHandle $easyHandle): bool => $easyHandle->rawHandle === $handle))) {
            fatal_error("Transfer completed for easy handle, but it is not in the list of added handles.");
        }
        $error = null;
        if ($errorCode = $easyHandle->urlErrorCode($easyCode)) {
            $error = new Error(URLErrorDomain, $errorCode, new Dictionary([LocalizedFailureReasonErrorKey => curl_strerror($easyCode)]));
        }
        $easyHandle->completedTransfer($error);
    }
}
