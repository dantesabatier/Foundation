<?php

namespace Sabatier\Foundation\Networking;

/**
 * Constants for providing task priority hints to a host, used with the {@see URLSessionTask::priority} property.
 */
class URLSessionTaskPriority
{
    /** @var float The default URL session task priority, used implicitly for any task you have not prioritized. */
    final const default = 0.5;
    /** @var float A low URL session task priority, with a floating point value above the minimum of 0 and below the default value. */
    final const low = 0.0;
    /** @var float A high URL session task priority, with a floating point value above the default value and below the maximum of 1.0. */
    final const high = 1.0;
}
