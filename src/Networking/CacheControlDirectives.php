<?php

declare(strict_types=1);

namespace Sabatier\Foundation\Networking;

use Sabatier\Foundation\ArrayClass;

/** @internal */
final class CacheControlDirectives
{
    public ?int $maxAge = null;
    public ?int $sharedMaxAge = null;
    public bool $noCache = false;
    public bool $noStore = false;

    /** @param string $headerValue */
    public function __construct(public readonly string $headerValue)
    {
        $isWithArgument = function (string $part, string $named): ?int {
            if (str_starts_with($part, "$named=")) {
                $split = explode("=", $part);
                if (count($split) === 2) {
                    $argument = $split[1];
                    if (strlen($argument) >= 2 && str_starts_with($argument, "\"") && str_ends_with($argument, "\"")) {
                        $argument = substr($argument, 1, -1);
                    }
                    return $argument !== "" && ctype_digit($argument) ? (int)$argument : null;
                }
            }
            return null;
        };
        /** @var ArrayClass<string> $parts */
        $parts = new ArrayClass(explode(",", $this->headerValue))->map(fn(string $e): string => $e
                |> trim(...)
                |> strtolower(...));
        foreach ($parts as $part) {
            if ($part === "no-cache") {
                $this->noCache = true;
            } elseif ($part === "no-store") {
                $this->noStore = true;
            } elseif (($maxAge = $isWithArgument($part, "max-age")) !== null) {
                $this->maxAge = $maxAge;
            } elseif (($sharedMaxAge = $isWithArgument($part, "s-maxage")) !== null) {
                $this->sharedMaxAge = $sharedMaxAge;
            }
        }
    }
}
