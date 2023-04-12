<?php

namespace Sabatier\Foundation\Networking;

use Exception;
use Sabatier\Foundation\Dictionary;
use Sabatier\Foundation\Error;
use function Sabatier\Foundation\fatal_error;
use const Sabatier\Foundation\CocoaErrorDomain;
use const Sabatier\Foundation\UserCancelledError;

/**
 * @internal
 * @psalm-import-type DataCompletionHandler from URLSession
 * @psalm-import-type DownloadCompletionHandler from URLSession
 */
class ProtocolClient implements URLProtocolClient
{
    private ?string $cacheableData = null;
    private ?URLResponse $cacheableResponse = null;
    private URLCacheStoragePolicy $cachePolicy = URLCacheStoragePolicy::notAllowed;

    /**
     * @throws Exception
     */
    public function urlProtocolDidReceiveCacheStoragePolicy(URLProtocol $protocol, URLResponse $response, URLCacheStoragePolicy $policy): void
    {
        $task = $protocol->task;
        $session = $task->session;
        $task->response = $response;
        $this->cachePolicy = $policy;
        if ($session->configuration->urlCache) {
            switch ($policy) {
                case URLCacheStoragePolicy::allowed:
                case URLCacheStoragePolicy::allowedInMemoryOnly:
                    $this->cacheableData = "";
                    $this->cacheableResponse = $response;
                    break;
                case URLCacheStoragePolicy::notAllowed:
                    break;
            }
        }
        $behaviour = $session->behaviour($task);
        switch ($behaviour->rawValue) {
            case TaskBehaviourRawValue::taskDelegate:
                /** @var URLSessionDelegate $delegate */
                $delegate = $behaviour->taskDelegate;
                if ($delegate instanceof URLSessionDataDelegate && $task instanceof URLSessionDataTask) {
                    $delegate->urlSessionDataTaskDidReceiveResponse($session, $task, $response, function (URLSessionResponseDisposition $disposition): void {
                    });
                } elseif ($delegate instanceof URLSessionWebSocketDelegate && $task instanceof URLSessionWebSocketTask) {
                    $delegate->urlSessionWebSocketTaskDidOpenWithProtocol($session, $task, $task->protocolPicked);
                }
                break;
            case TaskBehaviourRawValue::noDelegate:
            case TaskBehaviourRawValue::dataCompletionHandler:
            case TaskBehaviourRawValue::downloadCompletionHandler:
                break;
        }
    }

    /**
     * @throws Exception
     */
    public function urlProtocolWasRedirectedToRedirectResponse(URLProtocol $protocol, URLRequest $request, URLResponse $response): void
    {
        fatal_error("The URLSession implementation doesn't currently handle redirects directly.");
    }

    public function urlProtocolCachedResponseIsValid(URLProtocol $protocol, CachedURLResponse $cachedResponse): void
    {
    }

    /**
     * @throws Exception
     */
    public function urlProtocolDidCancel(URLProtocol $protocol, URLAuthenticationChallenge $challenge): void
    {
        $this->urlProtocolTaskDidFailWithError($protocol->task, new Error(CocoaErrorDomain, UserCancelledError));
    }

    /**
     * @throws Exception
     */
    public function urlProtocolDidReceive(URLProtocol $protocol, URLAuthenticationChallenge $authenticationChallenge): void
    {
        $task = $protocol->task;
        $session = $task->session;
        $proceed = function (?URLCredential $credential) use ($authenticationChallenge, $task): void {
            $task->suspend();
            $protectionSpace = $authenticationChallenge->protectionSpace;
            $authenticationMethod = $protectionSpace->authenticationMethod;
            /** @var Challenge $challenge */
            $challenge = $protectionSpace->associatedValueForKey("challenge");
            $handler = $task->authHandler($authenticationMethod, $challenge);
            $handler($task, URLSessionAuthChallengeDisposition::useCredential, $credential);
            if ($credential) {
                $task->lastCredentialUsedFromStorageDuringAuthentication = (object)["protectionSpace" => $authenticationChallenge->protectionSpace, "credential" => $credential];
            } else {
                $task->lastCredentialUsedFromStorageDuringAuthentication = null;
            }
            $task->resume();
        };
        $attemptProceedingWithDefaultCredential = function () use ($authenticationChallenge, $task, $proceed): void {
            if ($credential = $authenticationChallenge->proposedCredential) {
                $last = $task->lastCredentialUsedFromStorageDuringAuthentication;
                if ($last?->credential !== $credential) {
                    $proceed($credential);
                } else {
                    $task->cancel();
                }
                return;
            }
            $proceed(null);
        };
        $delegate = $session->delegate;
        if ($delegate instanceof URLSessionTaskDelegate) {
            $delegate->urlSessionTaskDidReceiveChallenge($session, $task, $authenticationChallenge, function (URLSessionAuthChallengeDisposition $disposition, ?URLCredential $credential) use ($task, $proceed, $attemptProceedingWithDefaultCredential): void {
                switch ($disposition) {
                    case URLSessionAuthChallengeDisposition::useCredential:
                        $proceed($credential);
                        break;
                    case URLSessionAuthChallengeDisposition::performDefaultHandling:
                        $attemptProceedingWithDefaultCredential();
                        break;
                    case URLSessionAuthChallengeDisposition::cancelAuthenticationChallenge:
                    case URLSessionAuthChallengeDisposition::rejectProtectionSpace:
                        $task->cancel();
                        break;
                }
            });
        } else {
            $attemptProceedingWithDefaultCredential();
        }
    }

    /**
     * @throws Exception
     */
    public function urlProtocolDidFailWithError(URLProtocol $protocol, Error $error): void
    {
        $this->urlProtocolTaskDidFailWithError($protocol->task, $error);
    }

    /**
     * @throws Exception
     */
    public function urlProtocolTaskDidFailWithError(URLSessionTask $task, Error $error): void
    {
        $session = $task->session;
        $behaviour = $session->behaviour($task);
        switch ($behaviour->rawValue) {
            case TaskBehaviourRawValue::noDelegate:
                if ($task->state === URLSessionTaskState::completed) {
                    return;
                }
                $task->state = URLSessionTaskState::completed;
                $session->taskRegistry->remove($task);
                break;
            case TaskBehaviourRawValue::taskDelegate:
                if ($task->state === URLSessionTaskState::completed) {
                    return;
                }
                /** @var URLSessionDelegate $delegate */
                $delegate = $behaviour->taskDelegate;
                if ($delegate instanceof URLSessionTaskDelegate) {
                    $delegate->urlSessionTaskDidComplete($session, $task, $error);
                }
                $task->state = URLSessionTaskState::completed;
                $session->taskRegistry->remove($task);
                break;
            case TaskBehaviourRawValue::downloadCompletionHandler:
                if ($task->state === URLSessionTaskState::completed) {
                    return;
                }
                $task->state = URLSessionTaskState::completed;
                /** @var DownloadCompletionHandler $downloadCompletionHandler */
                $downloadCompletionHandler = $behaviour->downloadCompletionHandler;
                $downloadCompletionHandler(null, null, $error);
                break;
            case TaskBehaviourRawValue::dataCompletionHandler:
                if ($task->state === URLSessionTaskState::completed) {
                    return;
                }
                $task->state = URLSessionTaskState::completed;
                /** @var DataCompletionHandler $dataCompletionHandler */
                $dataCompletionHandler = $behaviour->dataCompletionHandler;
                $dataCompletionHandler(null, null, $error);
                $session->taskRegistry->remove($task);
                break;
        }
        $task->invalidateProtocol();
    }

    /**
     * @throws Exception
     */
    public function urlProtocolDidLoad(URLProtocol $protocol, string $data): void
    {
        $task = $protocol->task;
        if (!($request = $task->currentRequest)) {
            fatal_error();
        }
        $protocol::setProperty($data, "responseData", $request);
        switch ($this->cachePolicy) {
            case URLCacheStoragePolicy::allowed:
            case URLCacheStoragePolicy::allowedInMemoryOnly:
                /** @psalm-suppress PossiblyNullOperand */
                $this->cacheableData .= $data;
                break;
            case URLCacheStoragePolicy::notAllowed:
                break;
        }
        $session = $task->session;
        $behaviour = $session->behaviour($task);
        switch ($behaviour->rawValue) {
            case TaskBehaviourRawValue::taskDelegate:
                $delegate = $behaviour->taskDelegate;
                if ($delegate instanceof URLSessionDataDelegate && $task instanceof URLSessionDataTask) {
                    $delegate->urlSessionDataTaskReceiveData($session, $task, $data);
                } elseif ($task instanceof URLSessionDownloadTask && $delegate instanceof URLSessionDownloadDelegate) {
                    $bytesWritten = strlen($data);
                    $task->countOfBytesReceived += $bytesWritten;
                    $delegate->urlSessionDownloadTaskDidWriteData($session, $task, $bytesWritten, $task->countOfBytesReceived, $task->countOfBytesExpectedToReceive);
                }
                break;
            default:
                break;
        }
    }

    /**
     * @throws Exception
     */
    public function urlProtocolDidFinishLoading(URLProtocol $protocol): void
    {
        $task = $protocol->task;
        $session = $task->session;
        $response = $task->response;
        if ($response instanceof HTTPURLResponse && $response->statusCode === HTTPStatusCode::unauthorized && ($protectionSpace = URLProtectionSpace::create($response))) {
            $proceed = function (?URLCredential $credential) use ($task, $response, $protectionSpace, $protocol): void {
                $last = $task->lastCredentialUsedFromStorageDuringAuthentication;
                $proposedCredential = $last?->credential !== $credential ? $credential : null;
                $authenticationChallenge = new URLAuthenticationChallenge($protectionSpace, $proposedCredential, $task->previousFailureCount, $response, null, new URLSessionAuthenticationChallengeSender());
                $task->previousFailureCount += 1;
                $this->urlProtocolDidReceive($protocol, $authenticationChallenge);
            };
            if ($storage = $session->configuration->urlCredentialStorage) {
                $storage->getCredentials($protectionSpace, $task, function (?Dictionary $credentials) use ($task, $storage, $protectionSpace, $proceed): void {
                    if (($firstKeyLexicographically = $credentials?->keys?->sort()->first())) {
                        /** @psalm-suppress PossiblyNullArrayAccess, PossiblyNullReference */
                        $proceed($credentials[$firstKeyLexicographically]);
                    } else {
                        $storage->getDefaultCredential($protectionSpace, $task, function (?URLCredential $credential) use ($proceed): void {
                            $proceed($credential);
                        });
                    }
                });
            } else {
                $proceed(null);
            }
            return;
        }
        if (($storage = $session->configuration->urlCredentialStorage) &&
            ($last = $task->lastCredentialUsedFromStorageDuringAuthentication)) {
            $storage->set($last->credential, $last->protectionSpace, $task);
        }
        if (($cache = $session->configuration->urlCache) &&
            ($data = $this->cacheableData) &&
            ($cacheableResponse = $this->cacheableResponse) &&
            $task instanceof URLSessionDataTask) {
            $cacheable = new CachedURLResponse($cacheableResponse, $data, $this->cachePolicy);
            $protocolAllows = $protocol instanceof NativeProtocol && $protocol->canCache($cacheable);
            if ($protocolAllows) {
                $delegate = $session->delegate;
                if ($delegate instanceof URLSessionDataDelegate) {
                    $delegate->urlSessionDataTaskWillCacheResponse($session, $task, $cacheable, function (?CachedURLResponse $actualCacheable) use ($task, $cache): void {
                        if ($actualCacheable) {
                            $cache->storeCachedResponseForDataTask($actualCacheable, $task);
                        }
                    });
                } else {
                    $cache->storeCachedResponseForDataTask($cacheable, $task);
                }
            }
        }
        /** @var URLRequest $request */
        $request = $task->currentRequest;
        $behaviour = $session->behaviour($task);
        switch ($behaviour->rawValue) {
            case TaskBehaviourRawValue::noDelegate:
                if ($task->state === URLSessionTaskState::completed) {
                    return;
                }
                $task->state = URLSessionTaskState::completed;
                $session->taskRegistry->remove($task);
                break;
            case TaskBehaviourRawValue::taskDelegate:
                if ($task->state === URLSessionTaskState::completed) {
                    return;
                }
                $delegate = $behaviour->taskDelegate;
                if ($delegate instanceof URLSessionTaskDelegate) {
                    if ($delegate instanceof URLSessionDownloadDelegate && $task instanceof URLSessionDownloadTask) {
                        $delegate->urlSessionDownloadTaskDidFinishDownloadingToURL($session, $task, $protocol::property("temporaryFileURL", $request));
                    } elseif ($delegate instanceof URLSessionWebSocketDelegate && $task instanceof URLSessionWebSocketTask) {
                        $delegate->urlSessionWebSocketTaskDidCloseWithReason($session, $task, $task->closeCode, $task->closeReason);
                    }
                    $delegate->urlSessionTaskDidComplete($session, $task);
                }
                $task->state = URLSessionTaskState::completed;
                $session->taskRegistry->remove($task);
                break;
            case TaskBehaviourRawValue::dataCompletionHandler:
                if ($task->state === URLSessionTaskState::completed) {
                    return;
                }
                $task->state = URLSessionTaskState::completed;
                /** @var DataCompletionHandler $dataCompletionHandler */
                $dataCompletionHandler = $behaviour->dataCompletionHandler;
                $dataCompletionHandler($protocol::property("responseData", $request), $response, null);
                $session->taskRegistry->remove($task);
                break;
            case TaskBehaviourRawValue::downloadCompletionHandler:
                if ($task->state === URLSessionTaskState::completed) {
                    return;
                }
                /** @var DownloadCompletionHandler $downloadCompletionHandler */
                $downloadCompletionHandler = $behaviour->downloadCompletionHandler;
                $downloadCompletionHandler($protocol::property("temporaryFileURL", $request), $response, null);
                $session->taskRegistry->remove($task);
                break;
        }
        $task->invalidateProtocol();
    }
}
