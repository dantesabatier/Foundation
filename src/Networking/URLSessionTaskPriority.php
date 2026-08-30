<?php

declare(strict_types=1);

namespace Sabatier\Foundation\Networking;

/**
 * Constants for providing task priority hints to a host, used with the {@see URLSessionTask::priority} property.
 */
final class URLSessionTaskPriority
{
    /** @var float The default URL session task priority, used implicitly for any task you have not prioritized. */
    const float default = 0.5;
    /** @var float A low URL session task priority, with a floating point value above the minimum of 0 and below the default value. */
    const float low = 0.0;
    /** @var float A high URL session task priority, with a floating point value above the default value and below the maximum of 1.0. */
    const float high = 1.0;
}
