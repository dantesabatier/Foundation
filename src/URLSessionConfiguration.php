<?php

namespace Sabatier\Foundation;

use JetBrains\PhpStorm\Pure;

/**
 * Class URLSessionConfiguration
 * A configuration object that defines behavior and policies for a URL session.
 * @package Sabatier\Foundation
 * @psalm-consistent-constructor
 */
final class URLSessionConfiguration
{
    /** @var Dictionary<string>|null A dictionary of additional headers to send with requests.
     * This property specifies additional headers that are added to all tasks within sessions based on this configuration. For example, you might set the User-Agent header so that it is automatically included in every request your app makes through sessions based on this configuration. */
    public ?Dictionary $httpAdditionalHeaders = null;
    /** @var float|int The timeout interval to use when waiting for additional data. */
    public float|int $timeoutIntervalForRequest = 60.0;

    /**
     * A default session configuration object.
     */
    #[Pure]
    public static function default(): URLSessionConfiguration
    {
        return new URLSessionConfiguration();
    }
}
