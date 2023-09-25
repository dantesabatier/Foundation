<?php

namespace Sabatier\Foundation;

use Throwable;

class ErrorException extends \ErrorException
{
    use Debuggable;

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
            default => throw new UndefinedKeyException(sprintf("%s is not key value coding compliant for the key \"%s\"", $this->debugDescription(), $name))
        };
    }
}
