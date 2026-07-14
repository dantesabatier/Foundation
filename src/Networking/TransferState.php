<?php

declare(strict_types=1);

namespace Sabatier\Foundation\Networking;

use Sabatier\Foundation\URL;
use function Sabatier\Foundation\fatal_error;

/** @internal */
final class TransferState
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
        if (!($header = $this->parsedResponseHeader->byAppending($data, fn(string $headerLine): bool => empty($headerLine)))) {
            fatal_error();
        }
        if ($header->rawVale === ParsedResponseHeaderRawVale::complete) {
            if (!($response = $header->lines->createHTTPURLResponse($this->url))) {
                fatal_error();
            }
            return new TransferState($this->url, $this->parsedResponseHeader, $response, $this->bodyDataDrain);
        }
        return new TransferState($this->url, $header, $this->response, $this->bodyDataDrain);
    }

    public function byAppendingFTP(string $data, int $contentLength): TransferState
    {
        if (str_starts_with($data, (string)FTPHeaderCode::transferCompleted->value)) {
            return $this;
        }
        if (!($header = $this->parsedResponseHeader->byAppending($data, fn(): bool => str_starts_with($data, (string)FTPHeaderCode::dataConnectionOpen->value) || str_starts_with($data, (string)FTPHeaderCode::openDataConnection->value)))) {
            fatal_error();
        }
        if ($header->rawVale === ParsedResponseHeaderRawVale::complete) {
            return new TransferState($this->url, $this->parsedResponseHeader, $header->lines->createURLResponse($this->url, $contentLength), $this->bodyDataDrain);
        }
        return new TransferState($this->url, $header, $this->response, $this->bodyDataDrain);
    }

    public function byAppendingBodyData(string $data): TransferState
    {
        return match ($this->bodyDataDrain->rawValue) {
            DataDrainRawValue::inMemory => (function () use ($data): TransferState {
                $bodyData = $this->bodyDataDrain->bodyData;
                $bodyData .= $data;
                return new TransferState($this->url, $this->parsedResponseHeader, $this->response, DataDrain::inMemory($bodyData));
            })(),
            DataDrainRawValue::toFile => (function () use ($data): TransferState {
                /** @noinspection PhpUnhandledExceptionInspection */
                $this->bodyDataDrain->fileHandle?->write($data);
                return $this;
            })(),
            DataDrainRawValue::ignore => $this
        };
    }

    public function isHeaderComplete(): bool
    {
        return $this->response !== null;
    }
}
