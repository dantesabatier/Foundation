<?php

namespace Sabatier\Foundation\Networking;

use RuntimeException;
use Sabatier\Foundation\URL;

/** @internal */
class TransferState
{
    public URL $url;
    public ParsedResponseHeader $parsedResponseHeader;
    public ?URLResponse $response = null;
    public DataDrain $bodyDataDrain;

    public function __construct(URL $url, ?ParsedResponseHeader $parsedResponseHeader = null, ?URLResponse $response = null, ?DataDrain $bodyDataDrain = null)
    {
        $this->url = $url;
        $this->parsedResponseHeader = $parsedResponseHeader ?? ParsedResponseHeader::partial();
        $this->response = $response;
        $this->bodyDataDrain = $bodyDataDrain ?? DataDrain::inMemory();
    }

    public function byAppendingHTTP(string $data): TransferState
    {
        if (!($h = $this->parsedResponseHeader->byAppending($data, fn(string $headerLine): bool => empty($headerLine)))) {
            throw new RuntimeException();
        }
        if ($h->rawVale === ParsedResponseHeaderRawVale::complete) {
            $lines = $h->header;
            if (!($response = $lines->createHTTPURLResponse($this->url))) {
                throw new RuntimeException();
            }
            return new TransferState($this->url, $this->parsedResponseHeader, $response, $this->bodyDataDrain);
        } else {
            return new TransferState($this->url, $h, $this->response, $this->bodyDataDrain);
        }
    }

    public function byAppendingBodyData(string $data): TransferState
    {
        return match ($this->bodyDataDrain->rawValue) {
            DataDrainRawValue::inMemory => (function () use ($data): TransferState {
                $this->bodyDataDrain->bodyData .= $data;
                return new TransferState($this->url, $this->parsedResponseHeader, $this->response, DataDrain::inMemory($this->bodyDataDrain->bodyData));
            })(),
            DataDrainRawValue::toFile => new TransferState($this->url, $this->parsedResponseHeader, $this->response, $this->bodyDataDrain),
            DataDrainRawValue::ignore => $this
        };
    }

    public function isHeaderComplete(): bool
    {
        return $this->response !== null;
    }

    public function byAppendingFTP(string $data, int $contentLength): TransferState
    {
        if (str_starts_with($data, (string)FTPHeaderCode::transferCompleted->value)) {
            return $this;
        }
        if (!($h = $this->parsedResponseHeader->byAppending($data, fn(): bool => str_starts_with($data, (string)FTPHeaderCode::openDataConnection->value)))) {
            throw new RuntimeException();
        }
        if ($h->rawVale === ParsedResponseHeaderRawVale::complete) {
            if (!($response = $h->header->createURLResponse($this->url, $contentLength))) {
                throw new RuntimeException();
            }
            return new TransferState($this->url, $this->parsedResponseHeader, $response, $this->bodyDataDrain);
        } else {
            return new TransferState($this->url, $h, $this->response, $this->bodyDataDrain);
        }
    }
}
