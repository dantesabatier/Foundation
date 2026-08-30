<?php

declare(strict_types=1);

namespace Sabatier\Foundation\Networking;

use Sabatier\Foundation\Date;
use Sabatier\Foundation\URL;

/** @internal */
final class DiskEntry
{
    public const string pathExtension = "storedcachedurlresponse";
    private(set) Date $date {
        get => $this->date ??= new Date();
    }
    private(set) string $identifier {
        get => $this->identifier ??= md5($this->url->absoluteString);
    }

    private function __construct(public readonly URL $url)
    {
    }

    public static function entry(URL $url): ?DiskEntry
    {
        if ($url->pathExtension !== self::pathExtension) {
            return null;
        }
        /** @var string[] $parts */
        $parts = preg_quote(".", "/")
                |> (fn(string $x): string => sprintf("/%s/", $x))
                |> (fn(string $x): array => preg_split($x, $url->deletingPathExtension()->lastPathComponent, -1, PREG_SPLIT_NO_EMPTY));
        if (count($parts) !== 3) {
            return null;
        }
        [$t1, $t2, $identifier] = $parts;
        $entry = new DiskEntry($url);
        $entry->date = Date::dateWithTimeIntervalSinceReferenceDate((float)"$t1.$t2");
        $entry->identifier = $identifier;
        return $entry;
    }
}
