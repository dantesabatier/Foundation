<?php

namespace Sabatier\Foundation\Networking;

use Sabatier\Foundation\Date;
use Sabatier\Foundation\URL;
use function Sabatier\Foundation\string_is_equal;

/** @internal */
class DiskEntry
{
    public final const pathExtension = "storedcachedurlresponse";
    public readonly Date $date;
    public readonly string $identifier;

    private function __construct(public readonly URL $url)
    {
    }

    public static function entry(URL $url): ?DiskEntry
    {
        if (string_is_equal($url->pathExtension, self::pathExtension)) {
            return null;
        }
        $parts = preg_split(sprintf("/%s/", preg_quote(".", "/")), $url->deletingPathExtension()->lastPathComponent, -1, PREG_SPLIT_NO_EMPTY);
        if (count($parts) !== 2) {
            return null;
        }
        [$timeString, $identifier] = $parts;
        $entry = new DiskEntry($url);
        $entry->date = new Date((float)$timeString);
        $entry->identifier = $identifier;
        return $entry;
    }
}
