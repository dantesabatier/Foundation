<?php

namespace Sabatier\Foundation;

use ErrorException;
use Throwable;

/**
 * An exception that occurs when an internal assertion fails and implies an unexpected condition within the called code.
 */
class InternalInconsistencyException extends ErrorException implements CustomDebugStringConvertible
{
    protected(set) Error $error {
        get => $this->error ??= new Error(CocoaErrorDomain, $this->code, new Dictionary([LocalizedDescriptionKey => localized_string("An unexpected error has occurred"), LocalizedFailureReasonErrorKey => $this->message ?: null]));
    }
    public string $description {
        get => sprintf("<%s %s> code=%d severity=%d, file=%s line=%d error=%s", class_name(get_class($this)), spl_object_id($this), $this->code, $this->severity, $this->file, $this->line, $this->error->description);
    }
    public string $debugDescription {
        get => $this->description;
    }

    public function __construct(string $message = "", int $code = 0, int $severity = E_ERROR, ?string $filename = null, ?int $line = null, ?Throwable $previous = null, ?Error $error = null)
    {
        if ($filename === null || $line === null) {
            $trace = debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS);
            $caller = $trace[0];
            foreach ($trace as $frame) {
                if (isset($frame["file"], $frame["line"]) &&  ($frame["function"] ?? "") !== "__construct") {
                    $caller = $frame;
                    break;
                }
            }
            $filename ??= $caller["file"] ?? "unknown";
            $line ??= $caller["line"] ?? 0;
        }
        parent::__construct($message, $code, $severity, $filename, $line, $previous);
        if ($error !== null) {
            $this->error = $error;
        }
    }

    public function __toString(): string
    {
        return $this->description;
    }
}
