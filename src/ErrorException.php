<?php

namespace Sabatier\Foundation;

use Throwable;

class ErrorException extends \ErrorException implements CustomDebugStringConvertible
{
    public Error $error {
        get => $this->error ??= new Error(CocoaErrorDomain, $this->getCode(), new Dictionary([LocalizedDescriptionKey => "An unexpected error has occurred", LocalizedFailureReasonErrorKey => $this->getMessage()]));
    }
    public string $description {
        get => sprintf("<%s %s>", class_name(get_called_class()), spl_object_id($this));
    }
    public string $debugDescription {
        get => sprintf("<%s %s>", class_name(get_called_class()), spl_object_id($this));
    }

    public function __construct(string $message = "", int $code = 0, int $severity = 1, ?string $filename = __FILE__, ?int $line = __LINE__, ?Throwable $previous = null, ?Error $error = null)
    {
        parent::__construct($message, $code, $severity, $filename, $line, $previous);
        if ($error) {
            $this->error = $error;
        }
    }
}
