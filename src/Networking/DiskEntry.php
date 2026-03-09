<?php

namespace Sabatier\Foundation\Networking;

use Sabatier\Foundation\Date;
use Sabatier\Foundation\URL;
use Sabatier\Foundation\UUID;

/** @internal */
final class DiskEntry
{
    final public const string pathExtension = "storedcachedurlresponse";
    private(set) Date $date {
        get => $this->date ??= new Date();
    }
    private(set) string $identifier {
        get => $this->identifier ??= new UUID()->uuidString;
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
        $entry->date = new Date((float)"$t1.$t2");
        $entry->identifier = $identifier;
        return $entry;
    }
}
