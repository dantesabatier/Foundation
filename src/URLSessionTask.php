<?php

/**
 * @author Dante Sabatier <dantesabatier@me.com>
 * @version 1.0
 * @package Sabatier\Foundation
 */

namespace Sabatier\Foundation;

use Closure;
use CURLFile;
use CurlHandle;

/**
 * Class URLSessionTask
 * A task, like downloading a specific resource, performed in a URL session.
 * @package Sabatier\Foundation
 */
abstract class URLSessionTask extends ObjectClass
{
    /** @internal */
    public readonly CurlHandle $ch;
    /** @internal */
    public URLSession $session;
    /** @var URLResponse|null The server's response to the currently active request. */
    public readonly ?URLResponse $response;
    /** @var URLSessionTaskState The current state of the task—active, suspended, in the process of being canceled, or completed. */
    public URLSessionTaskState $state = URLSessionTaskState::suspended;
    /** @var float The relative priority at which you'd like a host to handle the task, specified as a floating point value between 0.0 (lowest priority) and 1.0 (highest priority). To provide hints to a host on how to prioritize URL session tasks from your app, specify a priority for each task. Specifying a priority provides only a hint and does not guarantee performance. If you don't specify a priority, a URL session task has a priority of {@see URLSessionTaskPriority::default}, with a value of 0.5. There are three named priorities you can employ, described in {@see URLSessionTaskPriority}. */
    public float $priority = URLSessionTaskPriority::default;
    /** @var float A representation of the overall task progress. */
    public float $progress = 0.0;
    /** @var float The number of bytes that the task expects to receive in the response body. This value is determined based on the Content-Length header received from the server. If that header is absent, the value is {@see URLSessionTransferSizeUnknown}. */
    public float $countOfBytesExpectedToReceive = URLSessionTransferSizeUnknown;
    /** @var float The number of bytes that the task has received from the server in the response body. */
    public float $countOfBytesReceived = 0.0;
    /** @var float The number of bytes that the task expects to send in the request body. */
    public float $countOfBytesExpectedToSend = URLSessionTransferSizeUnknown;
    /** @var float The number of bytes that the task has sent to the server in the request body. */
    public float $countOfBytesSent = 0.0;
    /** @var Dictionary<string> */
    private readonly Dictionary $headerFields;
    private bool $isExecuting = false;

    /**
     * @param URLRequest $request A URL request object that provides the URL, cache policy, request type, body data or body stream, and so on.
     * @param Closure(string|null, URLResponse|null, Error|null): void $completion The completion handler to call when the load request is complete.
     */
    private function __construct(public readonly URLRequest $request, public readonly Closure $completion)
    {
        unset($this->ch);
        unset($this->headerFields);
    }

    public function __get(string $name)
    {
        if ($name == 'ch') {
            $ch = curl_init();
            curl_setopt($ch, CURLOPT_URL, $this->request->url->absoluteString);
            curl_setopt($ch, CURLOPT_CUSTOMREQUEST, $this->request->httpMethod);
            curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, $this->request->timeoutInterval);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
            curl_setopt($ch, CURLOPT_AUTOREFERER, true);
            curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 2);
            curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
            curl_setopt($ch, CURLOPT_BUFFERSIZE, 1024);
            curl_setopt($ch, CURLOPT_NOPROGRESS, false);
            curl_setopt($ch, CURLOPT_PROGRESSFUNCTION, function (mixed $client, float $countOfBytesExpectedToReceive, float $countOfBytesReceived, float $countOfBytesExpectedToSend, float $countOfBytesSent): int {
                if ($this instanceof URLSessionUploadTask) {
                    if ($this->countOfBytesExpectedToSend == URLSessionTransferSizeUnknown) {
                        if ($countOfBytesExpectedToSend > FlOAT_EPSILON) {
                            $this->willChangeValueForKey('countOfBytesExpectedToSend');
                            $this->countOfBytesExpectedToSend = $countOfBytesExpectedToSend;
                            $this->didChangeValueForKey('countOfBytesExpectedToSend');
                        }
                    } else {
                        $this->willChangeValueForKey('countOfBytesSent');
                        $this->countOfBytesSent = $countOfBytesSent;
                        $this->didChangeValueForKey('countOfBytesSent');
                        $this->willChangeValueForKey('progress');
                        $this->progress = round(($this->countOfBytesSent / $this->countOfBytesExpectedToSend) * 100, 2);
                        $this->didChangeValueForKey('progress');
                    }
                } elseif ($this instanceof URLSessionDownloadTask) {
                    if ($this->countOfBytesExpectedToReceive == URLSessionTransferSizeUnknown) {
                        if ($countOfBytesExpectedToReceive > FlOAT_EPSILON) {
                            $this->willChangeValueForKey('countOfBytesExpectedToReceive');
                            $this->countOfBytesExpectedToReceive = $countOfBytesExpectedToReceive;
                            $this->didChangeValueForKey('countOfBytesExpectedToReceive');
                        }
                    } else {
                        $this->willChangeValueForKey('countOfBytesReceived');
                        $this->countOfBytesReceived = $countOfBytesReceived;
                        $this->didChangeValueForKey('countOfBytesReceived');
                        $this->willChangeValueForKey('progress');
                        $this->progress = round(($this->countOfBytesReceived / $this->countOfBytesExpectedToReceive) * 100, 2);
                        $this->didChangeValueForKey('progress');
                    }
                }
                if ($this->state == URLSessionTaskState::canceled) {
                    return NotFound;
                }
                return CURLE_OK;
            });
            if (URLSession::$debugLevel) {
                curl_setopt($ch, CURLOPT_VERBOSE, true);
            }
            if ($body = $this->request->httpBody) {
                if ($this instanceof URLSessionUploadTask) {
                    $body = ['file' => new CURLFile($body, mime_content_type($body), basename($body))];
                }
                curl_setopt($ch, CURLOPT_POSTFIELDS, $body);
            }
            if ($httpAdditionalHeaders = $this->session->configuration->httpAdditionalHeaders) {
                foreach ($httpAdditionalHeaders as $key => $value) {
                    $this->request->setValueForHttpHeaderField($value, $key);
                }
            }
            if ($allHTTPHeaderFields = $this->request->allHTTPHeaderFields) {
                curl_setopt($ch, CURLOPT_HTTPHEADER, $allHTTPHeaderFields->mapValues(fn(string $value, string $key): string => "$key: $value")->values->toArray());
            }
            curl_setopt($ch, CURLOPT_ENCODING, '');
            curl_setopt($ch, CURLOPT_HEADER, false);
            curl_setopt($ch, CURLINFO_HEADER_OUT, false);
            curl_setopt($ch, CURLOPT_HEADERFUNCTION, function (mixed $client, string $field): int {
                $bytes = strlen($field);
                $fields = explode(':', $field, 2);
                if (count($fields) < 2) {
                    return $bytes;
                }
                [$key, $value] = $fields;
                $headerFields = $this->headerFields;
                $headerFields[trim($key)] = trim($value);
                return $bytes;
            });
            $this->$name = $ch;
            return $this->$name;
        } elseif ($name == 'headerFields') {
            $this->$name = new Dictionary();
            return $this->$name;
        } else {
            return $this->valueForUndefinedKey($name);
        }
    }

    /**
     * Creates a task that retrieves the contents of a URL based on the specified URL request object, and calls a handler upon completion.
     * @param URLRequest $request A URL request object that provides the URL, cache policy, request type, body data or body stream, and so on.
     * @param Closure(string|null, URLResponse|null, Error|null): void $completion The completion handler to call when the load request is complete.
     * @return static The new session data task.
     * @psalm-suppress UnsafeInstantiation
     */
    public static function taskWithRequest(URLRequest $request, Closure $completion): static
    {
        return new static($request, $completion);
    }

    /**
     * Resumes the task, if it is suspended.
     */
    public function resume(): void
    {
        if ($this->state == URLSessionTaskState::suspended) {
            $this->willChangeValueForKey('state');
            $this->state = URLSessionTaskState::running;
            $this->didChangeValueForKey('state');
            if (!$this->isExecuting) {
                $this->isExecuting = true;
                $this->session->execute($this, function (?string $data, int $code, ?Error $error): void {
                    $this->state = URLSessionTaskState::completed;
                    $this->response = $error ? null : new HTTPURLResponse($this->request->url, $code, null, $this->headerFields);
                    ($this->completion)($data, $this->response, $error);
                });
            } else {
                curl_pause($this->ch, CURLPAUSE_CONT);
            }
        }
    }

    /**
     * Temporarily suspends a task.
     */
    public function suspend(): void
    {
        if ($this->state == URLSessionTaskState::running) {
            $this->willChangeValueForKey('state');
            $this->state = URLSessionTaskState::suspended;
            $this->didChangeValueForKey('state');
            curl_pause($this->ch, CURLPAUSE_ALL);
        }
    }

    /**
     * Cancels the task.
     */
    public function cancel(): void
    {
        if ($this->state !== URLSessionTaskState::canceled) {
            $this->willChangeValueForKey('state');
            $this->state = URLSessionTaskState::canceled;
            $this->didChangeValueForKey('state');
        }
    }
}
