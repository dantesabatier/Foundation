<?php

namespace Sabatier\Foundation\Networking;

use Closure;
use Sabatier\Foundation\ArrayClass;

/** @internal */
class CacheControlDirectives
{
    public ?int $maxAge = null;
    public ?int $sharedMaxAge = null;
    public bool $noCache = false;
    public bool $noStore = false;

    public function __construct(public readonly string $headerValue)
    {
        $isWithArgument = function (string $part, string $named, Closure $converter): mixed {
            if (str_starts_with($part, "$named=")) {
                $split = explode("=", $part);
                if (count($split) === 2) {
                    $argument = $split[1];
                    if ($argument[0] === "\"" && $argument[strlen($argument) - 1] === "\"") {
                        if (strlen($argument) >= 2) {
                            return substr($argument, 1, strlen($argument) - 2);
                        } else {
                            return null;
                        }
                    } else {
                        return $converter($argument);
                    }
                }
            }
            return null;
        };
        /** @var ArrayClass<string> $parts */
        $parts = (new ArrayClass(explode(",", $this->headerValue)))->map(fn(string $e): string => strtolower(trim($e)));
        foreach ($parts as $part) {
            if ($part === "no-cache") {
                $this->noCache = true;
            } elseif ($part === "no-store") {
                $this->noStore = true;
            } elseif ($maxAge = $isWithArgument($part, "max-age", fn(string $e0): int => (int)$e0)) {
                $this->maxAge = $maxAge;
            } elseif ($sharedMaxAge = $isWithArgument($part, "s-maxage", fn(string $e0): int => (int)$e0)) {
                $this->sharedMaxAge = $sharedMaxAge;
            }
        }
    }
}
