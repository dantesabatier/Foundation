<?php

namespace Sabatier\Foundation\Networking;

use CURLFile;
use Exception;
use Locale;
use Sabatier\Foundation\ComparisonResult;
use Sabatier\Foundation\Date;
use Sabatier\Foundation\Dictionary;
use Sabatier\Foundation\Error;
use Sabatier\Foundation\ProcessInfo;
use Sabatier\Foundation\URL;
use Sabatier\Foundation\URLFileTypeMappings;
use function Sabatier\Foundation\fatal_error;
use const Sabatier\Foundation\CocoaErrorDomain;
use const Sabatier\Foundation\LocalizedDescriptionKey;
use const Sabatier\Foundation\URLErrorDomain;
use const Sabatier\Foundation\URLErrorFailingURLErrorKey;
use const Sabatier\Foundation\URLErrorHTTPTooManyRedirects;
use const Sabatier\Foundation\URLErrorUnknown;

/** @internal */
class HTTPURLProtocol extends NativeProtocol
{
    public int $redirectCount = 0;

    public static function canInit(URLRequest $request): bool
    {
        return match ($request->url->scheme) {
            "http", "https" => true,
            default => false,
        };
    }

    public function canCache(CachedURLResponse $cacheable): bool
    {
        $response = $cacheable->response;
        if (!$response instanceof HTTPURLResponse || !($request = $this->task->currentRequest)) {
            return false;
        }
        if ($response->allHeaderFields["WWW-Authenticate"] || $response->allHeaderFields["Proxy-Authenticate"] || $response->allHeaderFields["Authorization"] || $response->allHeaderFields["Proxy-Authorization"]) {
            return false;
        }
        switch ($request->httpMethod) {
            case HTTPRequestMethod::get:
                break;
            case HTTPRequestMethod::head:
                if (!empty($cacheable->data)) {
                    return false;
                }
                break;
            default:
                return false;
        }
        $now = new Date();
        if ($dateString = $response->allHeaderFields["Date"]) {
            $date = new Date((float)strtotime($dateString));
            $expirationStart = $date->compare($cacheable->date) === ComparisonResult::orderedDescending ? $date : $cacheable->date;
        } else {
            $expirationStart = $cacheable->date;
        }
        $hasCacheControl = false;
        $hasMaxAge = false;
        /** @var string|null $cacheControl */
        $cacheControl = $response->allHeaderFields["Cache-Control"];
        if ($cacheControl !== null) {
            $directives = new CacheControlDirectives($cacheControl);
            if ($directives->noCache || $directives->noStore) {
                return false;
            }
            if ($maxAge = $directives->maxAge) {
                $hasMaxAge = true;
                $expiration = $expirationStart->addingTimeInterval((float)$maxAge);
                if ($now->timeIntervalSinceReferenceDate >= $expiration->timeIntervalSinceReferenceDate) {
                    return false;
                }
            }
            if ($directives->sharedMaxAge) {
                $hasMaxAge = true;
            }
            $hasCacheControl = true;
        }
        /** @phpstan-ignore-next-line */
        if (!$hasCacheControl && $cacheControl !== null && in_array("no-cache", array_map(fn(string $part): string => strtolower(trim($part)), explode(",", $cacheControl)))) {
            return false;
        }
        switch ($response->statusCode) {
            case HTTPStatusCode::ok;
            case HTTPStatusCode::nonAuthoritativeInformation:
            case HTTPStatusCode::noContent:
            case HTTPStatusCode::partialContent:
            case HTTPStatusCode::multipleChoices:
            case HTTPStatusCode::movedPermanently:
            case HTTPStatusCode::notFound:
            case HTTPStatusCode::methodNotAllowed:
            case HTTPStatusCode::gone:
            case HTTPStatusCode::requestedURLTooLong:
            case HTTPStatusCode::unimplemented:
                break;
            default:
                return false;
        }
        if ($response->allHeaderFields["Vary"]) {
            return false;
        }
        if (!$hasMaxAge && ($expires = $response->allHeaderFields["Expires"])) {
            $expiration = new Date((float)strtotime($expires));
            if ($now->timeIntervalSinceReferenceDate >= $expiration->timeIntervalSinceReferenceDate) {
                return false;
            }
        }
        return true;
    }

    public function canRespondFromCache(CachedURLResponse $cachedResponse): bool
    {
        if (!$this->canCache($cachedResponse)) {
            return parent::canRespondFromCache($cachedResponse);
        }
        return true;
    }

    /**
     * @throws Exception
     */
    public function configureEasyHandle(URLRequest $request, TaskBody $body): void
    {
        if ($request->httpMethod === HTTPRequestMethod::get && $body->rawValue !== TaskBodyRawValue::none) {
            trigger_error("GET method must not have a body");
            $this->internalState = InternalState::transferFailed();
            $error = new Error(CocoaErrorDomain, -1, new Dictionary([LocalizedDescriptionKey => "Resource exceeds maximum size", URLErrorFailingURLErrorKey => $request->url]));
            $this->transferCompleted($error);
            return;
        }
        $easyHandle = $this->easyHandle;
        $easyHandle->setVerboseModeOn(self::enableLibcurlDebugOutput());
        $easyHandle->setPassHeadersToDataStream(false);
        $easyHandle->setProgressMeterOff(true);
        $easyHandle->setSkipAllSignalHandling(true);
        $easyHandle->setFailOnHTTPErrorCode(false);
        $easyHandle->setURL($request->url);
        $easyHandle->setSessionConfig($this->task->session->configuration);
        $easyHandle->setAllowedProtocolsToHTTPAndHTTPS();
        $easyHandle->setPreferredReceiveBufferSize(PHP_INT_MAX);
        try {
            switch ($body->rawValue) {
                case TaskBodyRawValue::none:
                    /** @var DataDrain $dataDrain */
                    $dataDrain = $this->internalState->transferState?->bodyDataDrain;
                    if ($dataDrain->rawValue == DataDrainRawValue::toFile) {
                        $easyHandle->set($dataDrain->fileHandle, CURLOPT_FILE);
                    }
                    $easyHandle->setRequestBodyLength(0);
                    break;
                case TaskBodyRawValue::data:
                case TaskBodyRawValue::file:
                case TaskBodyRawValue::stream:
                    if ($data = $body->data) {
                        $easyHandle->set($data, CURLOPT_POSTFIELDS);
                    } elseif ($fileURL = $body->fileURL) {
                        $easyHandle->set(["file" => new CURLFile($fileURL->path, URLFileTypeMappings::shared()->mimeType($fileURL->pathExtension) ?? "application/octet-stream", $fileURL->lastPathComponent)], CURLOPT_POSTFIELDS);
                    }
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
            $error = new Error(URLErrorDomain, URLErrorUnknown, new Dictionary([LocalizedDescriptionKey => "File system error"]));
            $this->failWithError($error, $request);
            return;
        }
        $easyHandle->setFollowLocation(false);
        $easyHandle->setRequestMethod($request->httpMethod);
        $easyHandle->setTimeout((int)$request->timeoutInterval);
        $easyHandle->setAutomaticBodyDecompression(true);
        $easyHandle->setNoBody($request->httpMethod === HTTPRequestMethod::head);
        /** @var Dictionary<string> $customHeaders */
        $customHeaders = $request->allHTTPHeaderFields ?? new Dictionary();
        $customHeaders["Connection"] = "keep-alive";
        $customHeaders["User-Agent"] = sprintf("%s (unknown version) curl/%s %s/%s (%s)", ProcessInfo::processInfo()->processName, curl_version()["version"], php_uname("s"), php_uname("r"), php_uname("m"));
        $customHeaders["Accept-Language"] = Locale::getPrimaryLanguage(Locale::getDefault());
        if ($body->rawValue !== TaskBodyRawValue::none) {
            $customHeaders["Expect"] = "";
        }
        if ($request->httpMethod === HTTPRequestMethod::post && $request->valueForHttpHeaderField("Content-Type") === null && $request->httpBody !== null) {
            $customHeaders["Content-Type"] = "application/x-www-form-urlencoded";
        }
        $easyHandle->setCustomHeaders($customHeaders);
    }

    /**
     * @throws Exception
     */
    public function completionAction(URLRequest $request, URLResponse $response): CompletionAction
    {
        $httpResponse = $response;
        if (!$httpResponse instanceof HTTPURLResponse) {
            fatal_error("Response was not HTTPURLResponse");
        }
        if ($request = $this->redirectRequest($request, $httpResponse)) {
            return CompletionAction::redirectWithRequest($request);
        }
        return CompletionAction::completeTask();
    }

    /**
     * @throws Exception
     */
    public function redirectFor(URLRequest $request): void
    {
        if ($this->internalState->rawValue !== InternalStateRawValue::transferCompleted) {
            fatal_error("Trying to redirect, but the transfer is not complete.");
        }
        $task = $this->task;
        $this->redirectCount += 1;
        if ($this->redirectCount > 16) {
            $this->internalState = InternalState::transferFailed();
            $error = new Error(URLErrorDomain, URLErrorHTTPTooManyRedirects, new Dictionary([LocalizedDescriptionKey => "Too many HTTP redirects"]));
            $request = $task->currentRequest ?? fatal_error("In a redirect chain but no current request");
            $this->failWithError($error, $request);
            return;
        }
        /** @var TransferState $ts */
        $ts = $this->internalState->transferState;
        $session = $task->session;
        $delegate = $session->delegate;
        if ($delegate instanceof URLSessionTaskDelegate) {
            /** @var HTTPURLResponse $response */
            $response = $ts->response;
            $bodyDataDrain = $ts->bodyDataDrain;
            $this->internalState = InternalState::waitingForRedirectCompletionHandler($response, $bodyDataDrain);
            $delegate->urlSessionTaskWillPerformHTTPRedirection($session, $task, $response, $request, function (?URLRequest $request): void {
                $this->didCompleteRedirectCallback($request);
            });
        } else {
            $configuredRequest = $session->configuration->configure($request);
            $task->knownBody = TaskBody::none();
            $this->startNewTransfer($configuredRequest);
        }
    }

    public function validateHeaderComplete(TransferState $transferState): ?URLResponse
    {
        if (!$transferState->isHeaderComplete()) {
            return new HTTPURLResponse($transferState->url, HTTPStatusCode::ok, "HTTP/0.9");
        }
        return null;
    }

    /**
     * @throws Exception
     */
    public function redirectRequest(URLRequest $request, HTTPURLResponse $response): ?URLRequest
    {
        if (!($location = $response->valueForHttpHeaderField("Location"))) {
            return null;
        }
        switch ($response->statusCode) {
            case HTTPStatusCode::movedPermanently:
            case HTTPStatusCode::found:
            case HTTPStatusCode::seeOther:
                $request->httpMethod = HTTPRequestMethod::get;
                $request->httpBody = null;
                break;
            case HTTPStatusCode::useProxy:
            case HTTPStatusCode::switchProxy:
            case HTTPStatusCode::temporaryRedirect:
            case HTTPStatusCode::permanentRedirect:
                break;
            default:
                return null;
        }
        $request->url = new URL($location);
        return $request;
    }

    /**
     * @throws Exception
     */
    public function didReceiveHeaderData(string $data, int $contentLength): EasyHandleAction
    {
        if ($this->internalState->rawValue !== InternalStateRawValue::transferInProgress) {
            fatal_error("Received header data, but no transfer in progress.");
        }
        try {
            /** @var TransferState $ts */
            $ts = $this->internalState->transferState;
            $newTS = $ts->byAppendingHTTP($data);
            $this->internalState = InternalState::transferInProgress($newTS);
            $didCompleteHeader = !$ts->isHeaderComplete() && $newTS->isHeaderComplete();
            if ($didCompleteHeader) {
                /** @var HTTPURLResponse $response */
                $response = $newTS->response;
                if (($contentEncoding = $response->allHeaderFields["Content-Encoding"]) && $contentEncoding !== "identity") {
                    $this->task->countOfBytesExpectedToReceive = URLSessionTransferSizeUnknown;
                } else {
                    $this->task->countOfBytesExpectedToReceive = $contentLength ?: URLSessionTransferSizeUnknown;
                }
                $this->didReceiveResponse();
            }
            return EasyHandleAction::proceed;
        } catch (Exception) {
            return EasyHandleAction::abort;
        }
    }

    /**
     * @throws Exception
     */
    public function didReceiveResponse(): void
    {
        if ($this->internalState->rawValue !== InternalStateRawValue::transferInProgress) {
            fatal_error("Transfer not in progress.");
        }
        $response = $this->internalState->transferState?->response;
        if (!$response instanceof HTTPURLResponse) {
            fatal_error("Header complete, but not URL response.");
        }
        $session = $this->task->session;
        $behaviour = $session->behaviour($this->task);
        switch ($behaviour->rawValue) {
            case TaskBehaviourRawValue::taskDelegate:
                switch ($response->statusCode) {
                    case HTTPStatusCode::movedPermanently:
                    case HTTPStatusCode::found:
                    case HTTPStatusCode::seeOther:
                    case HTTPStatusCode::notModified:
                    case HTTPStatusCode::temporaryRedirect:
                        break;
                    default:
                        $this->client?->urlProtocolDidReceiveCacheStoragePolicy($this, $response, URLCacheStoragePolicy::notAllowed);
                        break;
                }
                break;
            case TaskBehaviourRawValue::noDelegate:
            case TaskBehaviourRawValue::downloadCompletionHandler:
            case TaskBehaviourRawValue::dataCompletionHandler:
                break;
        }
    }

    /**
     * @throws Exception
     */
    private function didCompleteRedirectCallback(?URLRequest $request): void
    {
        if ($this->internalState->rawValue !== InternalStateRawValue::waitingForRedirectCompletionHandler) {
            fatal_error("Received callback for HTTP redirection, but we're not waiting for it. Was it called multiple times?");
        }
        if ($request) {
            $this->lastRedirectBody = null;
            $this->task->knownBody = TaskBody::none();
            $this->startNewTransfer($request);
        } else {
            /** @var TransferState $ts */
            $ts = $this->internalState->transferState;
            /** @var HTTPURLResponse $response */
            $response = $ts->response;
            $bodyDataDrain = $ts->bodyDataDrain;
            $this->client?->urlProtocolDidReceiveCacheStoragePolicy($this, $response, URLCacheStoragePolicy::notAllowed);
            if ($lastRedirectBody = $this->lastRedirectBody) {
                $this->client?->urlProtocolDidLoad($this, $lastRedirectBody);
            }
            $this->internalState = InternalState::transferCompleted($response, $bodyDataDrain);
            $this->completeTask();
        }
    }
}
