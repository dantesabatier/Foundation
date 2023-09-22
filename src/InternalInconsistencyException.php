<?php

namespace Sabatier\Foundation;

use ErrorException;
use Throwable;

/**
 * An exception that occurs when an internal assertion fails and implies an unexpected condition within the called code.
 */
class InternalInconsistencyException extends ErrorException
{
    public readonly Error $error;

    public function __construct(string $message = "", int $code = 0, int $severity = 1, ?string $filename = __FILE__, ?int $line = __LINE__, ?Throwable $previous = null, ?Error $error = null)
    {
        parent::__construct($message, $code, $severity, $filename, $line, $previous);
        unset($this->error);
        if ($error) {
            $this->error = $error;
        }
    }

    public function __get(string $name)
    {
        return $this->$name = match ($name) {
            "error" => new Error(CocoaErrorDomain, $this->getCode(), new Dictionary([LocalizedFailureReasonErrorKey => $this->getMessage()])),
            default => throw new UndefinedKeyException()
        };
    }
}
